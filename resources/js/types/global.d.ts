import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currency: {
                base: string;
                code: string;
                rates: Record<string, number>;
            };
            notifications: {
                unread_count: number;
                recent: Array<{
                    id: string;
                    title: string;
                    body: string;
                    url: string;
                    type: string;
                    read_at: string | null;
                    created_at: string;
                }>;
            };
            [key: string]: unknown;
        };
    }
}
