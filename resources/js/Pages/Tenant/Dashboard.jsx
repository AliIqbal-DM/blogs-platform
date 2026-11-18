import WriterLayout from '@/Layouts/WriterLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { tenant, auth } = usePage().props;

    return (
        <WriterLayout>
            <Head title={`Dashboard · ${tenant.name}`} />

            <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <p className="mt-1 text-sm text-zinc-600">
                Welcome back, {auth.user.name}.
            </p>

            <div className="mt-8 rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center">
                <p className="text-sm font-medium text-zinc-900">No posts yet</p>
                <p className="mt-1 text-sm text-zinc-600">
                    Writing posts for {tenant.name} is coming soon.
                </p>
            </div>
        </WriterLayout>
    );
}
