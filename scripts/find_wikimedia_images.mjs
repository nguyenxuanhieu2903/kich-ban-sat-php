const targets = {
  game_002: { name: 'Gương', query: 'antique mirror ornate filetype:bitmap' },
  game_005: { name: 'Lý Luận Hư Cấu', query: 'vintage typewriter desk filetype:bitmap' },
  game_006: { name: 'Ngôi Nhà Ngọt Ngào', query: 'abandoned gothic haunted house night filetype:bitmap' },
  game_007: { name: 'Yandere 1', query: 'red roses black background filetype:bitmap' },
  game_008: { name: 'Yandere 2', query: 'sealing wax letter envelope filetype:bitmap' },
  game_009: { name: 'Tuyết Hương Liên Hoàn Án', query: 'Forbidden City snow winter Beijing filetype:bitmap' },
  game_010: { name: 'Lên Đường', query: 'steam locomotive train fog station filetype:bitmap' },
  game_011: { name: '1 Giờ 30', query: 'pocket watch dial antique shadow filetype:bitmap' },
  game_012: { name: 'Giày Thêu Hoa', query: 'Chinese red lanterns night temple filetype:bitmap' },
  game_016: { name: 'Vòng Lặp Mưu Sát Đảo Mèo', query: 'stormy ocean rocky cliffs island waves filetype:bitmap' },
  game_017: { name: 'Bệnh Viện Phụ Sản Ái Âu', query: 'abandoned hospital corridor eerie dark filetype:bitmap' },
  game_018: { name: 'Cậu Bé Váy Đỏ', query: 'dark attic shadow eerie window filetype:bitmap' },
  game_023: { name: 'Thiên Hạ Đệ Nhất Tửu', query: 'Chinese wine jar tavern ceramic sword filetype:bitmap' },
  game_025: { name: '100 Cách Chết NYC', query: 'apothecary bottles poison vintage medicine filetype:bitmap' },
  game_026: { name: 'Thông Báo Tìm Người', query: 'night forest flashlight fog beam search filetype:bitmap' },
  game_027: { name: 'Thược Dược Đen', query: 'black dahlia dark flower gothic filetype:bitmap' },
  game_030: { name: 'Yandere 3', query: 'broken glass texture dark cracked reflection filetype:bitmap' },
  game_031: { name: 'Thanh Lâu', query: 'Chinese pavilion lanterns night ancient filetype:bitmap' },
  game_032: { name: 'Hậu Cung', query: 'Forbidden City Hall Supreme Harmony throne Beijing filetype:bitmap' },
  game_035: { name: 'Lý Xuyên Quái Đàm Bộ', query: 'Tokyo alley night rain neon reflection filetype:bitmap' },
  game_036: { name: 'Đền Thần Shimizu Hachiman', query: 'torii gate shinto shrine mist forest filetype:bitmap' },
  game_038: { name: 'Thi Khúc Li Biệt', query: 'violin sheet music vintage candle filetype:bitmap' },
  game_039: { name: 'Dư Hương', query: 'antique perfume bottle glass vintage filetype:bitmap' },
  game_042: { name: 'Lập Phương Quán', query: 'cube geometric labyrinth mystery dark chamber filetype:bitmap' },
};

for (const [id, meta] of Object.entries(targets)) {
  const url = 'https://commons.wikimedia.org/w/api.php?action=query&generator=search&gsrsearch=' + encodeURIComponent(meta.query) + '&gsrnamespace=6&gsrlimit=3&prop=imageinfo&iiprop=url|thumburl&iiurlwidth=720&format=json';
  try {
    const res = await fetch(url, { headers: { 'User-Agent': 'JubenshaBookingBot/1.0 (contact@example.com)' } });
    const data = await res.json();
    const pages = Object.values(data.query?.pages || {});
    console.log(`\n=== [${id}] ${meta.name} ===`);
    for (const p of pages) {
      if (p.imageinfo?.[0]?.thumburl) {
        console.log(`- ${p.title}\n  Thumb: ${p.imageinfo[0].thumburl}`);
      }
    }
  } catch (err) {
    console.error(`Error for ${id}: ${err.message}`);
  }
}
