// src/components/TermsOfUse.js
import React from 'react';
import { Container, Row, Col, Card, ListGroup } from 'react-bootstrap';
import { Link } from 'react-router-dom'; // Assuming you're using React Router for navigation
import './css/TermsOfUse.css'; // Adjusted path to point to css folder outside react-frontend

const TermsOfUse = () => {
    return (
        <Container className="my-5">
            <Row>
                <Col>
                    <Card>
                        <Card.Body>
                            <Card.Title className="text-center mb-4">
                                <h1>SkillsPrint Terms of Use</h1>
                            </Card.Title>
                            <Card.Subtitle className="mb-3 text-muted">
                                <strong>Effective Date:</strong> May 12, 2025
                            </Card.Subtitle>

                            <section className="mb-4">
                                <h2>1. Introduction</h2>
                                <p>
                                    Welcome to SkillsPrint ("we," "us," or "our"), a platform dedicated to providing
                                    reviews, tutorials, and resources for skill development. These Terms of Use ("Terms")
                                    govern your access to and use of our website, services, and content (collectively,
                                    the "Platform"). By accessing or using the Platform, you agree to be bound by these
                                    Terms. If you do not agree, please do not use the Platform.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>2. Eligibility</h2>
                                <p>
                                    You must be at least 13 years old to use the Platform. If you are under 18, you must
                                    have permission from a parent or legal guardian. By using the Platform, you
                                    represent that you meet these eligibility requirements.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>3. Services</h2>
                                <p>
                                    SkillsPrint provides access to skill-building resources, user-generated reviews, and
                                    tutorials. We reserve the right to modify, suspend, or discontinue any part of the
                                    Platform at any time without prior notice.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>4. User Conduct</h2>
                                <p>You agree to use the Platform responsibly and not to:</p>
                                <ListGroup variant="flush">
                                    <ListGroup.Item>Engage in illegal activities, harassment, or discrimination.</ListGroup.Item>
                                    <ListGroup.Item>
                                        Post or share content that is defamatory, obscene, or infringes on others’ rights.
                                    </ListGroup.Item>
                                    <ListGroup.Item>Upload viruses, malware, or other harmful code.</ListGroup.Item>
                                    <ListGroup.Item>
                                        Attempt to gain unauthorized access to the Platform or other users’ accounts.
                                    </ListGroup.Item>
                                </ListGroup>
                                <p className="mt-3">
                                    You are responsible for maintaining the confidentiality of your account credentials
                                    and for all activities under your account.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>5. Intellectual Property</h2>
                                <p>
                                    All content on the Platform, including text, graphics, logos, and software, is owned
                                    by SkillsPrint or its licensors and is protected by copyright, trademark, and other
                                    laws. You may use the content for personal, non-commercial purposes only, unless
                                    otherwise permitted by us in writing.
                                </p>
                                <p>
                                    By submitting content (e.g., reviews, comments, or tutorials) to the Platform, you
                                    grant SkillsPrint a worldwide, non-exclusive, royalty-free license to use, reproduce,
                                    modify, and display such content in connection with the Platform. You represent that
                                    you own or have the necessary rights to submit such content.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>6. Payment Terms</h2>
                                <p>
                                    Some features or resources on the Platform may require payment. All prices, payment
                                    methods, and refund policies will be clearly outlined at the time of purchase. You
                                    agree to pay all applicable fees and taxes. We reserve the right to change pricing at
                                    any time, with notice where required by law.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>7. Privacy</h2>
                                <p>
                                    Your privacy is important to us. Please review our{' '}
                                    <Link to="/privacy-policy">Privacy Policy</Link> to understand how we collect, use,
                                    and protect your personal information. By using the Platform, you consent to our
                                    data practices as described in the Privacy Policy.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>8. Limitation of Liability</h2>
                                <p>
                                    To the fullest extent permitted by law, SkillsPrint and its affiliates, officers, and
                                    employees will not be liable for any indirect, incidental, or consequential damages
                                    arising from your use of the Platform, including but not limited to loss of data,
                                    profits, or business opportunities. Our total liability for any claim will not exceed
                                    the amount you paid us, if any, for the use of the Platform.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>9. Disclaimers</h2>
                                <p>
                                    The Platform is provided "as is" without warranties of any kind, express or implied,
                                    including warranties of merchantability, fitness for a particular purpose, or
                                    non-infringement. We do not guarantee that the Platform will be uninterrupted,
                                    error-free, or free of harmful components.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>10. Termination</h2>
                                <p>
                                    We may suspend or terminate your access to the Platform at our discretion, with or
                                    without notice, for any violation of these Terms or for any other reason. You may
                                    terminate your account at any time by contacting us. Upon termination, your right to
                                    access the Platform will cease, but these Terms will continue to apply to prior
                                    actions.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>11. Dispute Resolution</h2>
                                <p>
                                    These Terms are governed by the laws of [Your State/Country, e.g., California, USA].
                                    Any disputes arising from these Terms or your use of the Platform will be resolved
                                    through binding arbitration in [Your City/State/Country], except where prohibited by
                                    law. You agree to waive any right to a jury trial or class action.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>12. Changes to Terms</h2>
                                <p>
                                    We may update these Terms at any time. We will notify you of material changes by
                                    posting the updated Terms on the Platform or by email. Your continued use of the
                                    Platform after such changes constitutes your acceptance of the new Terms.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>13. Third-Party Links</h2>
                                <p>
                                    The Platform may contain links to third-party websites or services. We are not
                                    responsible for the content, practices, or availability of these third-party sites.
                                    Your use of such sites is at your own risk.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>14. Force Majeure</h2>
                                <p>
                                    We will not be liable for any failure to perform our obligations under these Terms
                                    due to events beyond our reasonable control, such as natural disasters, cyberattacks,
                                    or government actions.
                                </p>
                            </section>

                            <section className="mb-4">
                                <h2>15. Contact Us</h2>
                                <p>If you have questions about these Terms, please contact us at:</p>
                                <p>
                                    SkillsPrint<br />
                                    Email: <a href="mailto:support@skillsprint.com">support@skillsprint.com</a>
                                    <br />
                                    Address: [Your Business Address, if applicable]
                                </p>
                            </section>
                        </Card.Body>
                    </Card>
                </Col>
            </Row>
        </Container>
    );
};

export default TermsOfUse;
