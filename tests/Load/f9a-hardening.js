import crypto from 'k6/crypto';
import exec from 'k6/execution';
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const profile = __ENV.F9A_PROFILE || 'smoke';
const baseline = profile === 'baseline';
const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const apiBaseUrl = (__ENV.F9A_API_BASE_URL || baseUrl).replace(/\/$/, '');
const manifest = JSON.parse(open(__ENV.F9A_MANIFEST || '../../storage/framework/testing/f9a-hardening-manifest.json'));

const loginDuration = new Trend('login_duration', true);
const itemOperationDuration = new Trend('item_operation_duration', true);
const checkoutDuration = new Trend('checkout_duration', true);
const paymentDuration = new Trend('payment_duration', true);
const dashboardDuration = new Trend('dashboard_duration', true);
const validErrors = new Rate('valid_request_errors');
const unexpected5xx = new Counter('unexpected_5xx');
const unexpected3xx = new Counter('unexpected_3xx');
const unexpected4xx = new Counter('unexpected_4xx');
const catalogErrors = new Counter('catalog_errors');
const checkoutErrors = new Counter('checkout_errors');
const paymentErrors = new Counter('payment_errors');
const loginErrors = new Counter('login_errors');
const dashboardErrors = new Counter('dashboard_errors');
let webAuthenticated = false;

const stages = (target) => baseline
  ? [{ duration: '2m', target }, { duration: '10m', target }, { duration: '1m', target: 0 }]
  : [{ duration: '10s', target }, { duration: '40s', target }, { duration: '10s', target: 0 }];

export const options = {
  scenarios: {
    catalog: { executor: 'ramping-vus', exec: 'catalog', startVUs: 0, stages: stages(baseline ? 12 : 3), gracefulStop: '10s' },
    pos: { executor: 'ramping-vus', exec: 'pos', startVUs: 0, stages: stages(baseline ? 6 : 1), gracefulStop: '30s' },
    authentication: { executor: 'ramping-vus', exec: 'authentication', startVUs: 0, stages: stages(baseline ? 2 : 1), gracefulStop: '10s' },
  },
  thresholds: {
    login_duration: ['p(95)<2000'],
    item_operation_duration: ['p(95)<750'],
    checkout_duration: ['p(95)<1500'],
    payment_duration: ['p(95)<1500'],
    dashboard_duration: ['p(95)<2000'],
    valid_request_errors: ['rate<0.01'],
    unexpected_5xx: ['count==0'],
  },
  noCookiesReset: true,
  discardResponseBodies: false,
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

function actors() {
  return manifest.tenants.flatMap((tenant) => tenant.cashiers.map((cashier) => ({ ...cashier, tenant })));
}

function actorForVu() {
  const available = actors();
  return available[(exec.vu.idInTest - 1) % available.length];
}

function headers(actor, extra = {}) {
  return {
    Accept: 'application/json',
    Authorization: `Bearer ${actor.token}`,
    'Content-Type': 'application/json',
    ...extra,
  };
}

function record(response, trend, expectedStatuses, errorCounter) {
  trend.add(response.timings.duration);
  const expected = expectedStatuses.includes(response.status);
  validErrors.add(!expected);
  errorCounter.add(expected ? 0 : 1);
  unexpected3xx.add(response.status >= 300 && response.status < 400 ? 1 : 0);
  unexpected4xx.add(response.status >= 400 && response.status < 500 ? 1 : 0);
  if (response.status >= 500) unexpected5xx.add(1);
  check(response, { [`expected ${expectedStatuses.join('/')}`]: () => expected });

  return expected;
}

function recordDashboard(response) {
  dashboardDuration.add(response.timings.duration);
  const ready = response.status === 200
    && !response.url.includes('/app/login');
  validErrors.add(!ready);
  dashboardErrors.add(ready ? 0 : 1);
  unexpected3xx.add(response.status >= 300 && response.status < 400 ? 1 : 0);
  unexpected4xx.add(response.status >= 400 && response.status < 500 ? 1 : 0);
  if (response.status >= 500) unexpected5xx.add(1);
  check(response, { 'dashboard operational-ready': () => ready });

  return ready;
}

function uuid(seed) {
  const hash = crypto.sha256(seed, 'hex');
  return `${hash.slice(0, 8)}-${hash.slice(8, 12)}-4${hash.slice(13, 16)}-a${hash.slice(17, 20)}-${hash.slice(20, 32)}`;
}

export function catalog() {
  const actor = actorForVu();
  const item = actor.tenant.items[(exec.scenario.iterationInTest + exec.vu.idInTest) % actor.tenant.items.length];
  const operation = exec.scenario.iterationInTest % 3;
  let response;
  if (operation === 0) {
    response = http.get(`${apiBaseUrl}/api/v1/items?search=${encodeURIComponent(item.kode)}&per_page=15`, { headers: headers(actor), tags: { endpoint: 'item_search' } });
  } else if (operation === 1) {
    response = http.get(`${apiBaseUrl}/api/v1/items/scan/${item.barcode}`, { headers: headers(actor), tags: { endpoint: 'item_scan' } });
  } else {
    response = http.get(`${apiBaseUrl}/api/v1/pos/transactions/${actor.status_transaction_id}/status`, { headers: headers(actor), tags: { endpoint: 'transaction_status' } });
  }
  record(response, itemOperationDuration, [200], catalogErrors);
  sleep(1);
}

export function pos() {
  const actor = actorForVu();
  const item = actor.tenant.items[(exec.scenario.iterationInTest + exec.vu.idInTest) % actor.tenant.items.length];
  const seed = `${profile}:${actor.id}:${exec.vu.idInTest}:${exec.scenario.iterationInTest}:${Date.now()}`;
  const checkoutKey = uuid(`checkout:${seed}`);
  const checkout = http.post(`${apiBaseUrl}/api/v1/pos/checkout`, JSON.stringify({
    items: [{ item_id: item.id, qty: 1, discount_amount: '0.00' }],
  }), {
    headers: headers(actor, { 'Idempotency-Key': checkoutKey }),
    tags: { endpoint: 'checkout' },
  });
  if (!record(checkout, checkoutDuration, [201], checkoutErrors)) {
    sleep(1);
    return;
  }

  const transactionId = checkout.json('data.id');
  const methodIndex = exec.scenario.iterationInTest % 5;
  let payment;
  if (methodIndex < 3) {
    payment = http.post(`${apiBaseUrl}/api/v1/pos/transactions/${transactionId}/pay-cash`, JSON.stringify({
      cash_received: '100.00',
    }), { headers: headers(actor), tags: { endpoint: 'pay_cash' } });
  } else {
    const method = methodIndex === 3 ? 'qris' : 'transfer';
    payment = http.post(`${apiBaseUrl}/api/v1/pos/transactions/${transactionId}/pay-manual`, JSON.stringify({
      method,
      reference: `F9A-${method}-${transactionId}`,
      note: 'Synthetic hardening payment',
    }), {
      headers: headers(actor, { 'Idempotency-Key': uuid(`payment:${seed}`) }),
      tags: { endpoint: `pay_${method}` },
    });
  }
  record(payment, paymentDuration, [200], paymentErrors);
  sleep(1);
}

export function authentication() {
  const actor = actorForVu();
  const response = http.post(`${apiBaseUrl}/api/v1/auth/login`, JSON.stringify({
    email: actor.email,
    password: actor.password,
    device_name: 'f9a-k6',
  }), { headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, tags: { endpoint: 'login' } });
  record(response, loginDuration, [200], loginErrors);

  if (!webAuthenticated) {
    webAuthenticated = authenticateWebSession(actor);
  }

  if (webAuthenticated) {
    const dashboard = http.get(`${baseUrl}/app`, { tags: { endpoint: 'dashboard' } });
    recordDashboard(dashboard);
  } else {
    validErrors.add(true);
    dashboardErrors.add(1);
  }

  // Stay below the contractual 5/minute API login boundary; limiter saturation
  // is exercised separately by f9a-conflicts.js.
  sleep(15);
}

function authenticateWebSession(actor) {
  const loginPage = http.get(`${baseUrl}/app/login`, { tags: { endpoint: 'web_login_page' } });
  if (loginPage.status !== 200) {
    if (loginPage.status >= 500) unexpected5xx.add(1);
    return false;
  }

  const snapshot = loginPage.html().find('[wire\\:snapshot]').first().attr('wire:snapshot');
  const csrfToken = loginPage.html().find('meta[name="csrf-token"]').first().attr('content');
  if (!snapshot || !csrfToken) return false;

  const update = http.post(`${baseUrl}/livewire/update`, JSON.stringify({
    components: [{
      snapshot,
      updates: {
        'data.email': actor.email,
        'data.password': actor.password,
        'data.remember': false,
      },
      calls: [{ path: '', method: 'authenticate', params: [] }],
    }],
  }), {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'X-Livewire': 'true',
    },
    tags: { endpoint: 'web_login' },
  });

  if (update.status >= 500) unexpected5xx.add(1);

  return update.status === 200 && update.json('components.0.effects.redirect') !== null;
}

export function handleSummary(data) {
  const path = __ENV.K6_SUMMARY_PATH || 'storage/framework/testing/f9a-k6-summary.json';
  return { [path]: JSON.stringify({ profile, generated_at: new Date().toISOString(), metrics: data.metrics }, null, 2) };
}
