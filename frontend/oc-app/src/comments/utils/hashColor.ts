function mix(n: number): number {
  n = ((n >>> 16) ^ n) * 0x45d9f3b | 0
  n = ((n >>> 16) ^ n) * 0x45d9f3b | 0
  n = (n >>> 16) ^ n
  return n >>> 0
}

export function hashToColor(hash: string): string {
  const n = mix(parseInt(hash.slice(0, 7), 16))
  const hue = n % 360
  const sat = 55 + (Math.floor(n / 360) % 20)
  const lit = 32 + (Math.floor(n / 7200) % 13)
  return `hsl(${hue}, ${sat}%, ${lit}%)`
}
