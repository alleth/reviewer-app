import React from 'react';
import { Link } from 'react-router-dom';
import LegalLayout, { Section, Bullets } from './LegalLayout';
import { CONTACT_EMAIL, LAST_UPDATED, REFUND_WINDOW_DAYS } from '../../legal';

export default function RefundPolicy() {
    return (
        <LegalLayout title="Refund Policy" updated={LAST_UPDATED}>
            <p>
                CareerPass sells one-time access passes to a digital reviewer. Because access is granted immediately
                and the content can be used right away, purchases are generally non-refundable. This Policy explains
                the specific situations in which we will consider a refund, and how to request one. It forms part of
                our <Link to="/terms" className="text-brand hover:text-brand-dark">Terms of Service</Link>.
            </p>

            <Section n={1} title="When you may be eligible for a refund">
                <p>We will normally grant a refund in these cases:</p>
                <Bullets
                    items={[
                        <><strong>Duplicate or accidental charge</strong> &mdash; you were charged more than once for
                            the same pass, or an obvious accidental purchase that you report before making
                            significant use of the reviewer.</>,
                        <><strong>Access not delivered</strong> &mdash; your payment was completed but your account
                            was not granted access, and our support team cannot resolve it for you.</>,
                        <><strong>Unresolved technical fault on our side</strong> &mdash; a confirmed defect in the
                            Platform prevents you from using the reviewer for a significant portion of your access
                            period, and we are unable to fix it or restore lost time within a reasonable period after
                            you report it.</>,
                        <><strong>Goodwill window</strong> &mdash; you request a refund within 48 hours of purchase
                            and have made only minimal use of the reviewer (a small number of lessons or practice
                            questions). Refunds in this case are at our reasonable discretion.</>,
                    ]}
                />
            </Section>

            <Section n={2} title="When a refund will not be granted">
                <Bullets
                    items={[
                        'Change of mind after you have substantially used the reviewer, practice tests, or mock exams.',
                        'You did not use your pass, or did not use it enough, before the access period expired. Access periods do not pause and are not extended for non-use.',
                        'Not passing the exam, or dissatisfaction with your exam result.',
                        'Dissatisfaction with the scope or coverage of the content, where that scope was described on the Explore or Plans pages before purchase.',
                        'The access period has already ended.',
                        'Your account was suspended or terminated for a breach of the Terms of Service.',
                        'Issues caused by your own device, browser, or internet connection, or by a third-party service outside our control.',
                    ]}
                />
            </Section>

            <Section n={3} title="How to request a refund">
                <p>
                    Email{' '}
                    <a href={`mailto:${CONTACT_EMAIL}`} className="text-brand hover:text-brand-dark">{CONTACT_EMAIL}</a>{' '}
                    from the email address on your CareerPass account within {REFUND_WINDOW_DAYS} days of the charge,
                    and include:
                </p>
                <Bullets
                    items={[
                        'The name on the account and the email address used;',
                        'The date of purchase, the pass you bought, and the amount;',
                        'The payment method used (for example, GCash, Maya, or card); and',
                        'A clear description of the reason for the request, with screenshots if it relates to a technical issue.',
                    ]}
                />
                <p>
                    We aim to acknowledge requests within 3 business days and to decide within 10 business days,
                    sometimes sooner. We may ask for additional information to verify the purchase.
                </p>
            </Section>

            <Section n={4} title="How approved refunds are paid">
                <p>
                    Approved refunds are returned to the original payment method through our payment provider where
                    possible. The time for the funds to appear depends on the provider and your bank &mdash;
                    typically a few business days for e-wallets and up to 15 business days for cards. Where a refund
                    is granted as a goodwill gesture, we may instead offer account credit or an extension of your
                    access period if you prefer. Non-refundable fees charged by the payment provider may be deducted
                    from a refund where permitted by law.
                </p>
            </Section>

            <Section n={5} title="Chargebacks">
                <p>
                    If you believe a charge is wrong, please contact us first &mdash; we can usually resolve it
                    faster than a bank dispute. Filing a chargeback or payment dispute without contacting us may
                    result in your account being suspended while the dispute is investigated.
                </p>
            </Section>

            <Section n={6} title="Your statutory rights">
                <p>
                    Nothing in this Policy limits any rights you may have under the Consumer Act of the Philippines
                    (Republic Act No. 7394) or other applicable law. If a mandatory legal right entitles you to a
                    remedy that this Policy does not provide, that legal right prevails.
                </p>
            </Section>

            <Section n={7} title="Changes to this Policy">
                <p>
                    We may update this Policy from time to time. The version that applies to your purchase is the one
                    published on the date you bought your pass. Material changes will be posted here with an updated
                    &ldquo;Last updated&rdquo; date.
                </p>
            </Section>

            <Section n={8} title="Contact">
                <p>
                    Refund questions and requests:{' '}
                    <a href={`mailto:${CONTACT_EMAIL}`} className="text-brand hover:text-brand-dark">{CONTACT_EMAIL}</a>.
                    See also our{' '}
                    <Link to="/terms" className="text-brand hover:text-brand-dark">Terms of Service</Link> and{' '}
                    <Link to="/privacy" className="text-brand hover:text-brand-dark">Privacy Policy</Link>.
                </p>
            </Section>
        </LegalLayout>
    );
}
