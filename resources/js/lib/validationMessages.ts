const validationMessages: Record<string, string> = {
    required: 'Ce champ est obligatoire.',
    required_unless:
        'Renseignez ce champ ou choisissez l’option alternative proposée.',
    required_if: 'Ce champ est obligatoire pour l’option choisie.',
    email: 'Saisissez une adresse e-mail valide.',
    unique: 'Cette valeur est déjà utilisée.',
    max: 'La valeur dépasse la limite autorisée.',
    min: 'La valeur est inférieure au minimum autorisé.',
    gt: 'La valeur doit être supérieure au minimum autorisé.',
    gte: 'La valeur est inférieure au minimum autorisé.',
    numeric: 'Saisissez un nombre valide.',
    integer: 'Saisissez un nombre entier.',
    date: 'Saisissez une date valide.',
    confirmed: 'Les deux valeurs ne correspondent pas.',
    exists: 'Cette sélection n’est plus disponible.',
    in: 'Choisissez une valeur dans la liste proposée.',
};

export function formatValidationError(message?: string): string {
    if (!message) return '';
    if (message === 'auth.failed')
        return 'Ces identifiants ne correspondent pas à nos enregistrements.';
    if (message === 'auth.password') return 'Le mot de passe est incorrect.';
    if (message === 'auth.throttle')
        return 'Trop de tentatives. Réessayez plus tard.';
    if (!message.startsWith('validation.')) return message;
    const rule = message.slice('validation.'.length).split('.')[0];
    return validationMessages[rule] ?? 'Vérifiez la valeur saisie.';
}
