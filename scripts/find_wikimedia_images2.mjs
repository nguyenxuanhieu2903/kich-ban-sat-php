const targets2 = {
  game_002: 'antique mirror frame',
  game_006: 'haunted house',
  game_011: 'pocket watch dial',
  game_016: 'stormy sea cliffs',
  game_017: 'abandoned hospital',
  game_018: 'creepy corridor abandoned',
  game_023: 'Chinese ceramic wine',
  game_025: 'apothecary bottles vintage',
  game_026: 'dark misty forest night',
  game_027: 'dahlia dark background',
  game_030: 'broken glass texture',
  game_031: 'Chinese traditional building night',
  game_036: 'torii shrine mist',
  game_038: 'violin sheet music',
  game_042: 'stone labyrinth chamber'
};

for (const [id, q] of Object.entries(targets2)) {
  const url = 'https://commons.wikimedia.org/w/api.php?action=query&generator=search&gsrsearch=' + encodeURIComponent(q + ' filetype:bitmap') + '&gsrnamespace=6&gsrlimit=3&prop=imageinfo&iiprop=url|thumburl&iiurlwidth=720&format=json';
  try {
    const res = await fetch(url, { headers: { 'User-Agent': 'JubenshaBookingBot/1.0 (contact@example.com)' } });
    const data = await res.json();
    const pages = Object.values(data.query?.pages || {});
    console.log(`\n=== [${id}] ${q} ===`);
    for (const p of pages) {
      if (p.imageinfo?.[0]?.thumburl) {
        console.log(`- ${p.title}\n  Thumb: ${p.imageinfo[0].thumburl}`);
      }
    }
  } catch (err) {
    console.error(`Error for ${id}: ${err.message}`);
  }
}
