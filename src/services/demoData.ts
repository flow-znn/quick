import type { Booking, Resource } from '../types';
export const resources: Resource[] = [
 { id: 1, name: 'Sala de Reunião 01', type: 'Sala', meta: 'Capacidade para 12 pessoas · Bloco A', description: 'Sala ampla com TV e quadro branco', location: 'Bloco A', capacity: '12 pessoas', initials: 'SR' },
 { id: 2, name: 'Laboratório de Informática', type: 'Laboratório', meta: '30 computadores · Bloco B', description: 'Computadores com softwares acadêmicos', location: 'Bloco B', capacity: '30 computadores', initials: 'LI' },
 { id: 3, name: 'Projetor Epson X49', type: 'Equipamento', meta: 'Projetor multimídia · Patrimônio 023', description: 'Projetor para apresentações e aulas', location: 'Patrimônio 023', initials: 'EP', unavailable: true },
 { id: 4, name: 'Laboratório de Redes', type: 'Laboratório', meta: '20 bancadas · Bloco C', description: 'Bancadas para práticas de redes', location: 'Bloco C', capacity: '20 bancadas', initials: 'LR' },
 { id: 5, name: 'Notebook Dell Latitude', type: 'Equipamento', meta: 'Notebook · Patrimônio 041', description: 'Notebook para uso em atividades', location: 'Patrimônio 041', initials: 'ND' },
 { id: 6, name: 'Sala de Estudos 02', type: 'Sala', meta: 'Capacidade para 8 pessoas · Bloco A', description: 'Ambiente para estudo em grupo', location: 'Bloco A', capacity: '8 pessoas', initials: 'SE', unavailable: true }
];
// Dados exclusivamente demonstrativos; substituir por chamadas à API quando o backend estiver disponível.
export const bookings: Booking[] = [
 { id: 1, resourceId: 1, responsible: 'Mariana Costa', date: '2026-11-12', start: '09:00', end: '10:00', status: 'ATIVA' },
 { id: 2, resourceId: 2, responsible: 'Lucas Ferreira', date: '2026-11-14', start: '14:00', end: '16:00', status: 'ATIVA' },
 { id: 3, resourceId: 3, responsible: 'Ana Beatriz', date: '2026-11-05', start: '10:00', end: '11:00', status: 'CONCLUIDA' },
 { id: 4, resourceId: 6, responsible: 'Pedro Henrique', date: '2026-11-02', start: '13:00', end: '14:00', status: 'CANCELADA' },
 { id: 5, resourceId: 4, responsible: 'Camila Souza', date: '2026-10-28', start: '08:00', end: '10:00', status: 'CONCLUIDA' },
 { id: 6, resourceId: 1, responsible: 'João Mendes', date: '2026-11-19', start: '15:00', end: '16:00', status: 'ATIVA' },
 { id: 7, resourceId: 5, responsible: 'Beatriz Lima', date: '2026-11-21', start: '11:00', end: '12:00', status: 'ATIVA' },
 { id: 8, resourceId: 2, responsible: 'Rafael Alves', date: '2026-11-25', start: '08:00', end: '10:00', status: 'ATIVA' }
];
