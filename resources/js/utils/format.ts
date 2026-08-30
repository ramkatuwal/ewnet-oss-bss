/**
 * Safely format coordinates
 */
export function formatCoordinate(value: any, decimals: number = 4): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num) || !isFinite(num)) {
        return '';
    }
    return num.toFixed(decimals);
}

/**
 * Safely format latitude/longitude pair
 */
export function formatCoordinates(lat: any, lng: any): string {
    const latStr = formatCoordinate(lat);
    const lngStr = formatCoordinate(lng);
    if (!latStr || !lngStr) {
        return '-';
    }
    return `${latStr}, ${lngStr}`;
}
