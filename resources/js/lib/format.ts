const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const BULAN_PANJANG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

export function rp(n: number | string | undefined | null): string {
  if (typeof n === 'string') n = parseFloat(n);
  if (n === undefined || n === null || isNaN(n)) return '–';
  const s = Math.round(Math.abs(n)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return (n < 0 ? '-Rp' : 'Rp') + s;
}
export function rpShort(n: number): string {
  if (Math.abs(n) >= 1e9) return 'Rp' + (n / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' M';
  if (Math.abs(n) >= 1e6) return 'Rp' + (n / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
  return rp(n);
}
export function parseMoney(s: string): number { const d = s.replace(/[^\d]/g, ''); return d ? parseInt(d, 10) : 0; }
export function fmtDate(iso?: string | null): string {
  if (!iso) return '–';
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  if (!y) return '–';
  return `${d} ${BULAN[m - 1]} ${y}`;
}
export function fmtDateLong(iso: string): string {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d));
  return `${HARI[dt.getUTCDay()]}, ${d} ${BULAN_PANJANG[m - 1]} ${y}`;
}
export function fmtDateTime(iso?: string): string {
  if (!iso) return '–';
  const t = iso.length > 10 ? iso.slice(11, 16) : '';
  return fmtDate(iso) + (t ? `, ${t} WIB` : '');
}
export function fmtMonth(ym: string): string { const [y, m] = ym.split('-').map(Number); return `${BULAN_PANJANG[m - 1]} ${y}`; }
export function hk(n: number): string { return `${n} hari kerja`; }
export function pct(n?: number): string { return n === undefined || n === null ? '–' : `${n.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`; }
export function fileSize(b: number): string { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
export function daysUntil(iso: string | null | undefined, today: string): number | null { if (!iso) return null; return Math.round((Date.parse(iso.slice(0, 10)) - Date.parse(today)) / 864e5); }
export function addDays(iso: string, n: number): string { const d = new Date(iso.slice(0, 10) + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); }
