import WriterLayout from '@/Layouts/WriterLayout';
import { Head, useForm } from '@inertiajs/react';
import Form, { toLocalInput, toPayload } from './Form';

export default function Edit({ post }) {
    const form = useForm({
        title: post.title,
        body: post.body,
        status: post.status,
        publish_at: toLocalInput(post.publish_at),
    });

    const submit = (e) => {
        e.preventDefault();

        form.transform(toPayload);
        form.put(route('tenant.dashboard.posts.update', post.id));
    };

    return (
        <WriterLayout>
            <Head title={`Edit · ${post.title}`} />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold tracking-tight">Edit post</h1>
                <p className="mt-1 font-mono text-xs text-zinc-600">/posts/{post.slug}</p>
            </div>

            <Form form={form} onSubmit={submit} submitLabel="Update post" />
        </WriterLayout>
    );
}
