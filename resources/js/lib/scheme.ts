/** Skema pengadaan; padanan App\Support\Scheme di server. */
export type Method = 'SPP Pusat' | 'SPP Kemitraan' | 'SPR';
export const METHODS: Method[] = ['SPP Pusat', 'SPP Kemitraan', 'SPR'];
/** SPP Pusat dan SPR melewati unit pengadaan (metode pengadaan, Rendan/Lakdan) dan berakhir di SP2K. */
export const viaProcurementUnit = (m: string | null | undefined) => m !== 'SPP Kemitraan';
/** Hanya SPP Pusat yang memakai metode pengadaan (rute Rendan/Lakdan). SPR: Pengaju -> Manager Pengadaan -> SP2K. */
export const usesProcMethod = (m: string | null | undefined) => m === 'SPP Pusat';
export const finalDoc = (m: string | null | undefined) => (m === 'SPP Kemitraan' ? 'PO' : 'SP2K');
export const numberLabel = (m: string | null | undefined) => (m === 'SPP Pusat' ? 'Nomor SPP' : m === 'SPR' ? 'Nomor SPR' : 'Nomor pengajuan');
export const dateLabel = (m: string | null | undefined) => (m === 'SPP Pusat' ? 'Tanggal SPP' : m === 'SPR' ? 'Tanggal SPR' : 'Tanggal pengajuan');
/** Nomor dan tanggal pengajuan wajib untuk SPP Pusat dan SPR. */
export const numberRequired = (m: string | null | undefined) => m !== 'SPP Kemitraan';
export const methodTone = (m: string | null | undefined): 'navy' | 'teal' | 'brand' => (m === 'SPP Kemitraan' ? 'teal' : m === 'SPR' ? 'brand' : 'navy');
