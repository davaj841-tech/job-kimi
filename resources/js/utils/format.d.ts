export function formatPrice(
  value: number | string | null | undefined,
  options?: { freeLabel?: string },
): string

export function formatDate(value: string | number | Date | null | undefined): string

export function formatDateTime(value: string | number | Date | null | undefined): string

export function toFaDigits(input: unknown): string

export function unwrapList(payload: unknown): unknown[]

export function unwrapMeta(payload: unknown): Record<string, unknown> | null

export function unwrapItem<T = unknown>(payload: unknown): T

export function apiErrorMessage(error: unknown, fallback?: string): string
