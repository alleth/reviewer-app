import React from 'react';
import { Link } from 'react-router-dom';
import LegalLayout, { Section, Bullets } from './LegalLayout';
import { CONTACT_EMAIL, COMPANY, VENUE, LAST_UPDATED } from '../../legal';

export default function TermsOfService() {
    return (
        <LegalLayout title="Terms of Service" updated={LAST_UPDATED}>
            <p>
                These Terms of Service (&ldquo;Terms&rdquo;) are an agreement between you and {COMPANY}
                (&ldquo;CareerPass,&rdquo; &ldquo;we,&rdquo; &ldquo;us,&rdquo; or &ldquo;our&rdquo;) governing your
                access to and use of the CareerPass website, applications, content, and services (the
                &ldquo;Platform&rdquo;). By creating an account, purchasing an access pass, or otherwise using the
                Platform, you agree to these Terms. If you do not agree, do not use the Platform.
            </p>

            <Section n={1} title="Eligibility">
                <p>
                    You must be at least 13 years old to use the Platform. If you are under 18, you may use the
                    Platform only with the involvement and consent of a parent or legal guardian, who agrees to be
                    bound by these Terms on your behalf. By using the Platform you represent that you meet these
                    requirements and that the information you provide is accurate.
                </p>
            </Section>

            <Section n={2} title="Your account">
                <Bullets
                    items={[
                        'You are responsible for keeping your login credentials confidential and for all activity that occurs under your account.',
                        'One person, one account. Do not share, sell, transfer, or let anyone else use your account.',
                        'For security, only one device can be signed in to an account at a time &mdash; signing in on a new device signs you out elsewhere. Repeated sign-ins from many devices in a short window may trigger an email verification step.',
                        'Tell us promptly at the contact address below if you believe your account has been compromised.',
                    ]}
                />
            </Section>

            <Section n={3} title="What CareerPass provides">
                <p>
                    CareerPass is an online reviewer that currently helps users prepare for the Civil Service
                    Examination (CSE-PPT). It provides study lessons, a practice question bank, mock exams, and
                    progress tracking. The content is for study and preparation purposes only and does not constitute
                    professional, legal, or career advice. We may add, change, suspend, or remove features, content,
                    or access passes at any time.
                </p>
            </Section>

            <Section n={4} title="No guarantee of results; not government-affiliated">
                <p>
                    We do not guarantee that using the Platform will result in a passing score or any particular exam
                    outcome. Results depend on many factors outside our control. CareerPass is an independent study
                    resource and is <strong>not affiliated with, endorsed by, or sponsored by</strong> the Civil
                    Service Commission (CSC) or any other government agency. Official examinations, schedules, and
                    results are governed solely by the relevant government body.
                </p>
            </Section>

            <Section n={5} title="Access passes and payment">
                <Bullets
                    items={[
                        'Access to the reviewer is sold as one-time passes for a fixed number of days (for example, 7, 30, or 90 days). Prices are shown in Philippine pesos and are inclusive of applicable taxes unless stated otherwise.',
                        'Passes are not subscriptions. Nothing renews automatically and you are never charged again to keep access.',
                        'Your access period begins when your payment is confirmed by the payment provider, and ends automatically when the period lapses. Your learning progress is retained so you can continue if you buy another pass.',
                        'Payments are collected and processed by a third-party payment provider. Your use of that provider is subject to its own terms and privacy policy.',
                        'We may change prices at any time; changes do not affect a pass you have already purchased.',
                        'Refunds are governed by our Refund Policy.',
                    ]}
                />
            </Section>

            <Section n={6} title="Acceptable use">
                <p>You agree not to:</p>
                <Bullets
                    items={[
                        'Copy, record, republish, distribute, sell, sublicense, or publicly display any part of the Platform&rsquo;s content, including questions, explanations, and lessons;',
                        'Scrape, crawl, bulk-download, or use automated means to extract the question bank or other content;',
                        'Share your account or access with others, or resell or commercialize your access;',
                        'Reverse engineer, decompile, or attempt to derive the source code of the Platform, or circumvent access controls, rate limits, or security features;',
                        'Upload or transmit malware or any harmful code, or attempt to gain unauthorized access to the Platform, its systems, or other users&rsquo; accounts;',
                        'Use the Platform to harass, abuse, defame, or infringe the rights of others, or for any unlawful purpose;',
                        'Interfere with or disrupt the integrity or performance of the Platform.',
                    ]}
                />
            </Section>

            <Section n={7} title="Intellectual property">
                <p>
                    The Platform and all of its content &mdash; text, questions, explanations, graphics, logos, the
                    CareerPass name and mark, and software &mdash; are owned by {COMPANY} or its licensors and are
                    protected by Philippine and international intellectual property laws. Subject to these Terms and
                    your active access pass, we grant you a limited, personal, non-exclusive, non-transferable,
                    revocable license to access and use the content for your own exam preparation. No other rights
                    are granted.
                </p>
            </Section>

            <Section n={8} title="Feedback and submissions">
                <p>
                    If you send us feedback, suggestions, or ideas about the Platform, you grant us a worldwide,
                    royalty-free, perpetual license to use them without restriction or obligation to you. You are
                    responsible for any content you submit and represent that you have the right to submit it.
                </p>
            </Section>

            <Section n={9} title="Third-party services">
                <p>
                    The Platform relies on and links to third-party services, including Google Sign-In, our payment
                    provider, and community platforms such as Facebook, X, and Discord. We are not responsible for
                    those services, their content, or their practices, and your use of them is governed by their own
                    terms.
                </p>
            </Section>

            <Section n={10} title="Suspension and termination">
                <p>
                    You may stop using the Platform at any time and may ask us to delete your account. We may
                    suspend or terminate your access, with or without notice, if you breach these Terms, if we are
                    required to by law, or to protect the Platform or other users. If we terminate your account for a
                    breach of these Terms, you are not entitled to a refund of any unused access. Sections that by
                    their nature should survive termination (including intellectual property, disclaimers, limitation
                    of liability, and governing law) will continue to apply.
                </p>
            </Section>

            <Section n={11} title="Disclaimers">
                <p>
                    The Platform is provided on an &ldquo;as is&rdquo; and &ldquo;as available&rdquo; basis. To the
                    fullest extent permitted by law, we disclaim all warranties, express or implied, including
                    warranties of merchantability, fitness for a particular purpose, accuracy, and non-infringement.
                    We do not warrant that the Platform will be uninterrupted, timely, secure, error-free, or that
                    any content is complete or accurate.
                </p>
            </Section>

            <Section n={12} title="Limitation of liability">
                <p>
                    To the fullest extent permitted by law, {COMPANY} and its owners, officers, employees, and
                    partners will not be liable for any indirect, incidental, special, consequential, or exemplary
                    damages, or for lost profits, data, goodwill, or opportunities, arising out of or relating to
                    your use of the Platform. Our total aggregate liability for any claim relating to the Platform
                    will not exceed the total amount you paid to us in the twelve (12) months before the event giving
                    rise to the claim. Nothing in these Terms excludes liability that cannot be excluded under
                    Philippine law.
                </p>
            </Section>

            <Section n={13} title="Indemnification">
                <p>
                    You agree to indemnify and hold harmless {COMPANY} from any claims, losses, liabilities, and
                    expenses (including reasonable legal fees) arising from your misuse of the Platform or your
                    breach of these Terms.
                </p>
            </Section>

            <Section n={14} title="Governing law and venue">
                <p>
                    These Terms are governed by the laws of the Republic of the Philippines, without regard to its
                    conflict-of-laws rules. Any dispute arising out of or relating to these Terms or the Platform
                    that cannot be resolved amicably will be submitted to the exclusive jurisdiction of the proper
                    courts of {VENUE}.
                </p>
            </Section>

            <Section n={15} title="Changes to these Terms">
                <p>
                    We may update these Terms from time to time. If a change is material, we will post the updated
                    Terms and update the &ldquo;Last updated&rdquo; date above, and where appropriate notify you by
                    email. Your continued use of the Platform after the change takes effect constitutes acceptance of
                    the revised Terms.
                </p>
            </Section>

            <Section n={16} title="Contact">
                <p>
                    Questions about these Terms can be sent to{' '}
                    <a href={`mailto:${CONTACT_EMAIL}`} className="text-brand hover:text-brand-dark">{CONTACT_EMAIL}</a>.
                    See also our{' '}
                    <Link to="/privacy" className="text-brand hover:text-brand-dark">Privacy Policy</Link> and{' '}
                    <Link to="/refund" className="text-brand hover:text-brand-dark">Refund Policy</Link>.
                </p>
            </Section>
        </LegalLayout>
    );
}
