export const murmurHash3 = async (key, seed) => {
  const encoder = new TextEncoder()
  const data = encoder.encode(key)

  let h1 = seed >>> 0
  const c1 = 0xcc9e2d51
  const c2 = 0x1b873593

  const remainder = data.length % 4
  const bytes = data.length - remainder

  let i = 0
  while (i < bytes) {
    let k1 = (data[i] & 0xff) |
             ((data[++i] & 0xff) << 8) |
             ((data[++i] & 0xff) << 16) |
             ((data[++i] & 0xff) << 24)
    i++

    k1 = (k1 * c1) | 0
    k1 = (k1 << 15) | (k1 >>> 17)
    k1 = (k1 * c2) | 0

    h1 ^= k1
    h1 = (h1 << 13) | (h1 >>> 19)
    h1 = (h1 * 5 + 0xe6546b64) | 0
  }

  let k1 = 0
  switch (remainder) {
    case 3:
      k1 ^= (data[i + 2] & 0xff) << 16
    case 2:
      k1 ^= (data[i + 1] & 0xff) << 8
    case 1:
      k1 ^= (data[i] & 0xff)
      k1 = (k1 * c1) | 0
      k1 = (k1 << 15) | (k1 >>> 17)
      k1 = (k1 * c2) | 0
      h1 ^= k1
  }

  h1 ^= data.length
  h1 ^= h1 >>> 16
  h1 = (h1 * 0x85ebca6b) | 0
  h1 ^= h1 >>> 13
  h1 = (h1 * 0xc2b2ae35) | 0
  h1 ^= h1 >>> 16

  return h1 >>> 0
}
