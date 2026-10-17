import { deadSessionReason, isNoActivePass, signedOutNotice } from './sessionErrors';

const failure = (status, url, data) => ({ response: { status, data }, config: { url } });

describe('deadSessionReason', () => {
    test.each([
        ['/api/topics', 'signed_in_elsewhere'],
        ['/api/topics/3', 'signed_out'],
        ['/api/questions/practice', 'signed_out'],
        ['https://api.career-pass.org/api/topics', 'signed_in_elsewhere'],
    ])('a 401 from content endpoint %s means the session is dead (%s)', (url, reason) => {
        expect(deadSessionReason(failure(401, url, { reason }))).toBe(reason);
    });

    test.each([
        ['/api/google-login'],
        ['/api/checkout'],
        ['/api/account'],
        ['/api/account/setup'],
        ['/api/purchases'],
        ['/api/billing/history'],
        ['/api/login'],
    ])('a 401 from %s is never treated as a dead session', (url) => {
        expect(deadSessionReason(failure(401, url, { reason: 'signed_out' }))).toBeNull();
    });

    test('other statuses and unknown reasons are ignored', () => {
        expect(deadSessionReason(failure(403, '/api/topics', { reason: 'no_active_pass' }))).toBeNull();
        expect(deadSessionReason(failure(503, '/api/topics', {}))).toBeNull();
        expect(deadSessionReason(failure(401, '/api/topics', {}))).toBeNull();
        expect(deadSessionReason(failure(401, '/api/topics', { reason: 'something_else' }))).toBeNull();
        expect(deadSessionReason({ message: 'Network Error' })).toBeNull();
        expect(deadSessionReason(undefined)).toBeNull();
    });
});

describe('isNoActivePass', () => {
    test('only the 403 no_active_pass counts', () => {
        expect(isNoActivePass(failure(403, '/api/topics', { reason: 'no_active_pass' }))).toBe(true);
        expect(isNoActivePass(failure(403, '/api/topics', { message: 'This endpoint is not available.' }))).toBe(false);
        expect(isNoActivePass(failure(401, '/api/topics', { reason: 'signed_out' }))).toBe(false);
        expect(isNoActivePass({})).toBe(false);
    });
});

describe('signedOutNotice', () => {
    test('maps a reason to the landing-page notice', () => {
        expect(signedOutNotice('signed_in_elsewhere')).toBe('elsewhere');
        expect(signedOutNotice('signed_out')).toBe('invalid');
        expect(signedOutNotice(null)).toBe('invalid');
    });
});
