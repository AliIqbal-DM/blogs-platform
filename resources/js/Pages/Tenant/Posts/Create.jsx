import WriterLayout from '@/Layouts/WriterLayout';
import { Head, useForm } from '@inertiajs/react';
import Form, { toPayload } from './Form';

export default function Create() {
    const form = useForm({
        title: '',
        body: '',
        status: 'draft',
        publish_at: '',
        cover: null,
    });

    const submit = (e) => {
        e.preventDefault();

        form.transform(toPayload);
        form.post(route('tenant.dashboard.posts.store'));
    };

    return (
        <WriterLayout>
            <Head title="New post" />

            <h1 className="mb-6 text-2xl font-semibold tracking-tight">New post</h1>

            <Form form={form} onSubmit={submit} submitLabel="Save post" />
        </WriterLayout>
    );
}
