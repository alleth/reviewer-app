import { formatWait, retryAfterSeconds, waitMessage } from './rateLimit';

const error = (status, data = {}, headers = {}) => ({ response: { status, data, headers } });

describe('retryAfterSeconds', () => {
    it('reads retryAfter from a 429 body', () => {
        expect(retryAfterSeconds(error(429, { reason: 'rate_limited', retryAfter: 27 }))).toBe(27);
    });

    it('falls back to the Retry-After header', () => {
        expect(retryAfterSeconds(error(429, {}, { 'retry-after': '900' }))).toBe(900);
    });

    it('rounds up and defaults to a minute when there is no usable number', () => {
        expect(retryAfterSeconds(error(429, { retryAfter: 1.2 }))).toBe(2);
        expect(retryAfterSeconds(error(429, { retryAfter: 'soon' }))).toBe(60);
        expect(retryAfterSeconds(error(429))).toBe(60);
    });

    it('is null for anything that is not a 429', () => {
        expect(retryAfterSeconds(error(400, { retryAfter: 30 }))).toBeNull();
        expect(retryAfterSeconds(error(401))).toBeNull();
        expect(retryAfterSeconds(new Error('Network Error'))).toBeNull();
        expect(retryAfterSeconds(undefined)).toBeNull();
    });
});

describe('formatWait / waitMessage', () => {
    it('shows minutes and seconds', () => {
        expect(formatWait(27)).toBe('0:27');
        expect(formatWait(900)).toBe('15:00');
        expect(formatWait(61.4)).toBe('1:02');
        expect(formatWait(-5)).toBe('0:00');
    });

    it('builds the countdown message', () => {
        expect(waitMessage(30)).toBe('Too many attempts. Please try again in 0:30.');
    });
});
