import React from 'react';
import { Link } from 'react-router-dom';
import LegalLayout, { Section, Bullets } from './LegalLayout';
import { CONTACT_EMAIL, COMPANY, COMPANY_ADDRESS, LAST_UPDATED } from '../../legal';

export default function PrivacyPolicy() {
    return (
        <LegalLayout title="Privacy Policy" updated={LAST_UPDATED}>
            <p>
                This Privacy Policy explains how {COMPANY} (&ldquo;CareerPass,&rdquo; &ldquo;we,&rdquo; &ldquo;us,&rdquo;
                or &ldquo;our&rdquo;) collects, uses, shares, and protects your personal information when you use
                the CareerPass website and its related services (the &ldquo;Platform&rdquo;). We process personal data in
                accordance with the Data Privacy Act of 2012 (Republic Act No. 10173), its Implementing Rules and
                Regulations, and issuances of the National Privacy Commission (NPC).
            </p>
            <p>
                By creating an account or using the Platform, you acknowledge that you have read and understood this
                Policy. If you do not agree with it, please do not use the Platform.
            </p>

            <Section n={1} title="Information we collect">
                <p>We collect the following categories of information:</p>
                <Bullets
                    items={[
                        <><strong>Account information</strong> &mdash; your first and last name, username, and email
                            address. If you register with a password, we store only a one-way hash of it, never the
                            password itself.</>,
                        <><strong>Google Sign-In data</strong> &mdash; if you choose to continue with Google, we
                            receive your Google account identifier, email address, and name from Google to create or
                            match your account.</>,
                        <><strong>Purchase information</strong> &mdash; the access pass you buy, the amount, and the
                            payment confirmation. Payments are processed by a third-party payment provider; we do not
                            receive or store your full card number, e-wallet PIN, or bank credentials.</>,
                        <><strong>Learning activity</strong> &mdash; your progress through the reviewer, practice and
                            mock exam attempts and scores, and which topics you have studied.</>,
                        <><strong>Technical and security data</strong> &mdash; IP address, browser and device type,
                            general location inferred from IP, pages viewed, and timestamps. For account security we
                            also record sign-in events with a hashed identifier derived from your browser&rsquo;s
                            user-agent string, used to detect unusual rapid device switching.</>,
                        <><strong>Communications</strong> &mdash; messages you send us for support, feedback, or refund
                            requests.</>,
                    ]}
                />
            </Section>

            <Section n={2} title="How we use your information">
                <Bullets
                    items={[
                        'Create and administer your account and provide the reviewer, practice tests, and progress tracking.',
                        'Authenticate you and protect your account &mdash; including enforcing one active session per account, the rapid device-switch check, and one-time email verification codes.',
                        'Process your purchases and grant, track, and expire your access period.',
                        'Send you transactional email such as verification codes, password-reset codes, and security notices (for example, when your password is changed). These are not marketing emails.',
                        'Respond to your support, feedback, and refund requests.',
                        'Monitor, debug, and improve the Platform and its content.',
                        'Comply with legal, tax, accounting, and regulatory obligations, and enforce our Terms of Service.',
                    ]}
                />
            </Section>

            <Section n={3} title="Legal bases for processing">
                <p>
                    We process your personal data on the bases permitted by the Data Privacy Act: performance of our
                    contract with you (providing the Platform and your purchased access), your consent (for example,
                    Google Sign-In), our legitimate interests (security, fraud prevention, and service improvement,
                    balanced against your rights), and compliance with legal obligations.
                </p>
            </Section>

            <Section n={4} title="Cookies and local storage">
                <p>
                    We use a session cookie to keep you signed in, and your browser&rsquo;s local storage to remember
                    your login state and your light/dark theme preference. These are necessary for the Platform to
                    function. We do not use third-party advertising or cross-site tracking cookies.
                </p>
            </Section>

            <Section n={5} title="How we share information">
                <p>We do not sell your personal data. We share it only as follows:</p>
                <Bullets
                    items={[
                        <><strong>Service providers</strong> &mdash; cloud hosting and content delivery, our
                            transactional email provider, and our payment provider, each acting on our instructions
                            and bound to protect your data.</>,
                        <><strong>Google</strong> &mdash; only when you choose Google Sign-In, and only to verify your
                            identity.</>,
                        <><strong>Legal and safety</strong> &mdash; when required by law, subpoena, or government
                            request, or to protect the rights, safety, and property of CareerPass, our users, or the
                            public.</>,
                        <><strong>Business transfers</strong> &mdash; in connection with a merger, acquisition, or sale
                            of assets, in which case we will require the recipient to honor this Policy.</>,
                    ]}
                />
            </Section>

            <Section n={6} title="Data retention">
                <p>
                    We keep your account information for as long as your account is active. Learning activity is kept
                    so you can resume where you left off between passes. Purchase records are retained as long as
                    required for tax and accounting law. One-time codes are deleted as soon as they are used or
                    expire, and sign-in event records are pruned automatically. When you ask us to delete your
                    account, we delete or anonymize your personal data within a reasonable period, except where we
                    must retain certain records to comply with law or resolve disputes.
                </p>
            </Section>

            <Section n={7} title="Security">
                <p>
                    We protect your data with measures including encrypted connections (HTTPS), one-way hashing of
                    passwords and one-time codes, restricted administrative access, and session controls. No method
                    of transmission or storage is completely secure, so we cannot guarantee absolute security. If we
                    become aware of a personal data breach that is likely to put you at risk, we will notify you and
                    the National Privacy Commission as required by law.
                </p>
            </Section>

            <Section n={8} title="Your rights">
                <p>Under the Data Privacy Act, you have the right to:</p>
                <Bullets
                    items={[
                        'Be informed about how your personal data is processed;',
                        'Access the personal data we hold about you;',
                        'Correct inaccurate or outdated personal data;',
                        'Object to processing or withdraw consent, where processing is based on consent;',
                        'Request erasure or blocking of your personal data, subject to legal limits;',
                        'Data portability &mdash; obtain a copy of the data you provided in a usable electronic format;',
                        'Lodge a complaint with the National Privacy Commission (privacy.gov.ph); and',
                        'Be indemnified for damages caused by inaccurate, incomplete, or unlawfully obtained personal data.',
                    ]}
                />
                <p>
                    To exercise any of these rights, contact us at{' '}
                    <a href={`mailto:${CONTACT_EMAIL}`} className="text-brand hover:text-brand-dark">{CONTACT_EMAIL}</a>{' '}
                    from the email address on your account. You can update your name, username, and email yourself on
                    the Settings page.
                </p>
            </Section>

            <Section n={9} title="Children">
                <p>
                    The Platform is intended for users who are at least 13 years old. If you are under 18, you must
                    have the consent of a parent or legal guardian. We do not knowingly collect personal data from
                    children under 13; if you believe we have, contact us and we will delete it.
                </p>
            </Section>

            <Section n={10} title="International transfers">
                <p>
                    Our service providers may store or process data on servers located outside the Philippines. Where
                    that happens, we take steps to ensure your data continues to receive a level of protection
                    consistent with the Data Privacy Act.
                </p>
            </Section>

            <Section n={11} title="Changes to this Policy">
                <p>
                    We may update this Policy from time to time. If we make a material change, we will post the
                    updated Policy on the Platform and update the &ldquo;Last updated&rdquo; date above, and where
                    appropriate notify you by email. Your continued use of the Platform after the change takes effect
                    means you accept the revised Policy.
                </p>
            </Section>

            <Section n={12} title="Contact us">
                <p>
                    For questions about this Policy or your personal data, contact our Data Protection Officer at{' '}
                    <a href={`mailto:${CONTACT_EMAIL}`} className="text-brand hover:text-brand-dark">{CONTACT_EMAIL}</a>.
                </p>
                <p>
                    {COMPANY}<br />
                    {COMPANY_ADDRESS}
                </p>
                <p>
                    See also our{' '}
                    <Link to="/terms" className="text-brand hover:text-brand-dark">Terms of Service</Link> and{' '}
                    <Link to="/refund" className="text-brand hover:text-brand-dark">Refund Policy</Link>.
                </p>
            </Section>
        </LegalLayout>
    );
}
