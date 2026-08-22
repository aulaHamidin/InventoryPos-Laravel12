import crypto from 'k6/crypto';
import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const manifest = JSON.parse(open(__ENV.F9A_MANIFEST || '../../storage/framework/testing/f9a-hardening-manifest.json'));
const expected409 = new Counter('expected_409');
const expected422 = new Counter('expected_422');
const expected429 = new Counter('expected_429');
const scenarioErrors = new Counter('scenario_errors');
const unexpected5xx = new Counter('unexpected_5xx');

export const options = {
  vus: 1,
  iterations: 1,
  thresholds: {
    expected_409: ['count==1'],
    expected_422: ['count==1'],
    expected_429: ['count==1'],
    scenario_errors: ['count==0'],
    unexpected_5xx: ['count==0'],
  },
};

function uuid(seed) {
  const hash = crypto.sha256(seed, 'hex');
  return `${hash.slice(0, 8)}-${hash.slice(8, 12)}-4${hash.slice(13, 16)}-a${hash.slice(17, 20)}-${hash.slice(20, 32)}`;
}

function headers(actor, key) {
  return {
    Accept: 'application/json',
    Authorization: `Bearer ${actor.token}`,
    'Content-Type': 'application/json',
    ...(key ? { 'Idempotency-Key': key } : {}),
  };
}

function verify(response, expectedStatus, counter = null) {
  const valid = response.status === expectedStatus;
  if (!valid) scenarioErrors.add(1);
  if (response.status >= 500) unexpected5xx.add(1);
  if (valid && counter) counter.add(1);
  check(response, { [`expected ${expectedStatus}`]: () => valid });

  return valid;
}

export default function () {
  const tenant = manifest.tenants[0];
  const actor = tenant.owner;
  const item = tenant.items[90];
  const seed = `f9a-conflict:${Date.now()}`;
  const checkoutKey = uuid(`checkout:${seed}`);
  const checkoutPayload = { items: [{ item_id: item.id, qty: 1, discount_amount: '0.00' }] };

  const checkout = http.post(`${baseUrl}/api/v1/pos/checkout`, JSON.stringify(checkoutPayload), {
    headers: headers(actor, checkoutKey),
  });
  if (!verify(checkout, 201)) return;

  const retry = http.post(`${baseUrl}/api/v1/pos/checkout`, JSON.stringify(checkoutPayload), {
    headers: headers(actor, checkoutKey),
  });
  const retryValid = verify(retry, 201) && retry.json('data.id') === checkout.json('data.id');
  if (!retryValid) scenarioErrors.add(1);

  const conflict = http.post(`${baseUrl}/api/v1/pos/checkout`, JSON.stringify({
    items: [{ item_id: item.id, qty: 2, discount_amount: '0.00' }],
  }), { headers: headers(actor, checkoutKey) });
  verify(conflict, 409, expected409);

  const transactionId = checkout.json('data.id');
  const paymentKey = uuid(`payment:${seed}`);
  const paymentPayload = { method: 'qris', reference: `F9A-CONFLICT-${transactionId}`, note: 'Synthetic retry proof' };
  const payment = http.post(`${baseUrl}/api/v1/pos/transactions/${transactionId}/pay-manual`, JSON.stringify(paymentPayload), {
    headers: headers(actor, paymentKey),
  });
  verify(payment, 200);
  const paymentRetry = http.post(`${baseUrl}/api/v1/pos/transactions/${transactionId}/pay-manual`, JSON.stringify(paymentPayload), {
    headers: headers(actor, paymentKey),
  });
  const paymentRetryValid = verify(paymentRetry, 200)
    && paymentRetry.json('data.transaction_id') === payment.json('data.transaction_id');
  if (!paymentRetryValid) scenarioErrors.add(1);

  const insufficient = http.post(`${baseUrl}/api/v1/pos/checkout`, JSON.stringify({
    items: [{ item_id: item.id, qty: 2_000_000, discount_amount: '0.00' }],
  }), { headers: headers(actor, uuid(`insufficient:${seed}`)) });
  verify(insufficient, 422, expected422);

  const missingEmail = `f9a-rate-${Date.now()}@example.test`;
  for (let attempt = 1; attempt <= 6; attempt += 1) {
    const response = http.post(`${baseUrl}/api/v1/auth/login`, JSON.stringify({
      email: missingEmail,
      password: 'invalid-synthetic-password',
      device_name: 'f9a-conflict',
    }), { headers: { Accept: 'application/json', 'Content-Type': 'application/json' } });
    verify(response, attempt === 6 ? 429 : 422, attempt === 6 ? expected429 : null);
  }
}

export function handleSummary(data) {
  const output = __ENV.K6_CONFLICT_SUMMARY_PATH || 'storage/framework/testing/f9a-k6-conflicts-summary.json';
  return { [output]: JSON.stringify({ generated_at: new Date().toISOString(), metrics: data.metrics }, null, 2) };
}
