export type ClinicProfile = {
    name: string | null;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    phone: string | null;
    email: string | null;
};

export type User = {
    id: number;
    name: string;
    email: string;
    clinic_profile: ClinicProfile | null;
    is_admin: boolean;
    avatar?: string;
    email_verified_at: string | null;
    last_login_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
