export type ResourceType = 'Sala' | 'Laboratório' | 'Equipamento';
export type BookingStatus = 'ATIVA' | 'CANCELADA' | 'CONCLUIDA';
export interface Resource { id: number; name: string; type: ResourceType; meta: string; description: string; location: string; capacity?: string; initials: string; unavailable?: boolean }
export interface Booking { id: number; resourceId: number; responsible: string; date: string; start: string; end: string; status: BookingStatus; notes?: string }
