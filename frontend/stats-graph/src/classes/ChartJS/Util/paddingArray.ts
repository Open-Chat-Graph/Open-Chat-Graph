const MIN_LEN = 7

export default function paddingArray<T>(array: T[], fillValue: T): T[] {
  const len = array.length
  if (len >= MIN_LEN) return array

  const padDiff = Math.ceil(len / 2)
  const padHorizontal = Math.ceil(MIN_LEN / 2) - padDiff

  const dummy: T[] = new Array(padHorizontal).fill(fillValue)

  return [...dummy, ...array, ...dummy]
}