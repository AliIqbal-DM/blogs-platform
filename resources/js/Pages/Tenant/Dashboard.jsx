import WriterLayout from '@/Layouts/WriterLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { tenant, auth } = usePage().props;

    return (
        <WriterLayout>
            <Head title={`Dashboard · ${tenant.name}`} />

            <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <p className="mt-1 text-sm text-zinc-600">
                Welcome back, {auth.user.name}.
            </p>

            <div className="mt-8 flex flex-wrap gap-3">
                <Link
                    href={route('tenant.dashboard.posts.index')}
                    className="cursor-pointer rounded-md bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-opacity duration-200 hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2"
                >
                    Manage posts
                </Link>
                <a
                    href={route('tenant.home')}
                    className="cursor-pointer rounded-md border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-800 transition-colors duration-200 hover:bg-zinc-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2"
                >
                    View {tenant.name}
                </a>
            </div>
        </WriterLayout>
    );
}
