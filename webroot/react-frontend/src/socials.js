import { FaFacebookF, FaPeopleGroup, FaXTwitter, FaDiscord } from 'react-icons/fa6';

/**
 * Single source of truth for the CareerPass community links. Import from here
 * (Explore page, footers, etc.) instead of hardcoding URLs per component.
 */
export const SOCIALS = [
    {
        id: 'facebook-page',
        name: 'Facebook Page',
        handle: 'CareerPass',
        blurb: 'Announcements, exam schedules and study tips in your feed.',
        href: 'https://www.facebook.com/people/CareerPass/61576540853180/',
        Icon: FaFacebookF,
    },
    {
        id: 'facebook-group',
        name: 'Facebook Community',
        handle: 'CareerPass Reviewers',
        blurb: 'Ask questions, swap notes and get motivation from fellow examinees.',
        href: 'https://www.facebook.com/groups/1538317484685691',
        Icon: FaPeopleGroup,
    },
    {
        id: 'x',
        name: 'X (Twitter)',
        handle: '@careerpassph',
        blurb: 'Quick updates and reminders as the exam date approaches.',
        href: 'https://x.com/careerpassph',
        Icon: FaXTwitter,
    },
    {
        id: 'discord',
        name: 'Discord Server',
        handle: 'CareerPass',
        blurb: 'Live study sessions, voice rooms and real-time help from the community.',
        href: 'https://discord.gg/Ht6UFFdq7',
        Icon: FaDiscord,
    },
];
