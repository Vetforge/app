export function focusFirstInvalidField(): void {
    const scope =
        document.querySelector('[role="dialog"]') ??
        document.querySelector('main') ??
        document.body;
    const field = Array.from(
        scope?.querySelectorAll<HTMLElement>(
            'input[aria-invalid="true"], select[aria-invalid="true"], textarea[aria-invalid="true"], button[aria-invalid="true"]',
        ) ?? [],
    ).find(
        (element) =>
            element.getClientRects().length > 0 &&
            !element.hasAttribute('disabled'),
    );
    field?.focus();
}
