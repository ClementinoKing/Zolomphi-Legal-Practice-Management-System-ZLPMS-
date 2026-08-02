import { Head } from '@inertiajs/react';
import MatterWorkspace from '@/components/matter-workspace';

type Props = Parameters<typeof MatterWorkspace>[0];

export default function MatterShow(props: Props) {
    return (
        <>
            <Head title={props.matter.title} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <MatterWorkspace {...props} />
            </div>
        </>
    );
}

MatterShow.layout = { breadcrumbs: [{ title: 'Matters', href: '/matters' }] };
