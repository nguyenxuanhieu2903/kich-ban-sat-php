import fs from 'node:fs';
import path from 'node:path';

const coversDir = path.resolve('frontend/assets/images/covers');

const replacements = {
  game_002: 'https://upload.wikimedia.org/wikipedia/commons/0/05/Twardowski_mirror.jpg',
  game_005: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/e8/Writing_the_Moment_%28Unsplash%29.jpg/960px-Writing_the_Moment_%28Unsplash%29.jpg',
  game_006: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d7/The_Old_Hall%2C_Fairies_by_Moonlight.jpg/960px-The_Old_Hall%2C_Fairies_by_Moonlight.jpg',
  game_007: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d7/Red_rose_with_black_background.jpg/960px-Red_rose_with_black_background.jpg',
  game_008: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c4/De_briefschrijfster%2C_RP-P-OB-33.485.jpg/960px-De_briefschrijfster%2C_RP-P-OB-33.485.jpg',
  game_009: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/6/61/Meridian_Gate_in_snow_%2820220213153145%29.jpg/960px-Meridian_Gate_in_snow_%2820220213153145%29.jpg',
  game_010: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/5/5a/Train_departing_in_a_cloud_of_steam_on_a_foggy_day.jpg/960px-Train_departing_in_a_cloud_of_steam_on_a_foggy_day.jpg',
  game_011: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/47/Vintage_Waltham_14_kt._Gold_Pocket_Watch%2C_Roman_Numeral_Dial_%288458964903%29.jpg/960px-Vintage_Waltham_14_kt._Gold_Pocket_Watch%2C_Roman_Numeral_Dial_%288458964903%29.jpg',
  game_012: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a2/Red_Lantern_above_the_Roof_Top_-_Flickr_-_LeonardKong.jpg/960px-Red_Lantern_above_the_Roof_Top_-_Flickr_-_LeonardKong.jpg',
  game_016: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/6/6b/British_%28English%29_School_-_Cliffs_with_Stormy_Sea_-_979786_-_National_Trust.jpg/960px-British_%28English%29_School_-_Cliffs_with_Stormy_Sea_-_979786_-_National_Trust.jpg',
  game_025: 'https://upload.wikimedia.org/wikipedia/commons/8/87/Poison.jpg',
  game_030: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/1/16/Glass_cobweb.jpg/960px-Glass_cobweb.jpg',
  game_031: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/6/64/Changdeokgung_Moonlight_Tour_Foreign_Media_18_%2817321027532%29.jpg/960px-Changdeokgung_Moonlight_Tour_Foreign_Media_18_%2817321027532%29.jpg',
  game_032: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/9/9c/Hall_of_Supreme_Harmony_%2854448353192%29.jpg/960px-Hall_of_Supreme_Harmony_%2854448353192%29.jpg',
  game_036: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a9/Itsukushima_Shrine_great_torii_in_mist_Miyajima.jpg/960px-Itsukushima_Shrine_great_torii_in_mist_Miyajima.jpg',
  game_038: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/8/85/Sheet_music_and_Violin.jpg/960px-Sheet_music_and_Violin.jpg',
  game_039: 'https://upload.wikimedia.org/wikipedia/commons/5/5b/R_Lalique_glass_9.JPG',
  game_042: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/46/Q%E2%80%99enqo%2C_Underground_Chamber.jpg/960px-Q%E2%80%99enqo%2C_Underground_Chamber.jpg',
};

console.log('Downloading 18 authentic replacements from Wikimedia Commons...');

for (const [id, url] of Object.entries(replacements)) {
  const filePath = path.join(coversDir, `${id}.jpg`);
  try {
    const res = await fetch(url, { headers: { 'User-Agent': 'JubenshaBookingBot/1.0 (contact@example.com)' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const buf = Buffer.from(await res.arrayBuffer());
    fs.writeFileSync(filePath, buf);
    console.log(`[SUCCESS] ${id}.jpg (${buf.length} bytes)`);
  } catch (err) {
    console.error(`[ERROR] ${id}: ${err.message}`);
  }
}
