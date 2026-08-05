import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatMoney(value: string | number): string {
  const n = typeof value === 'string' ? Number(value) : value
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export function formatQuantity(value: number): string {
  return value.toLocaleString('pt-BR')
}
