import fs from 'node:fs';
import path from 'node:path';

const coversDir = path.resolve('frontend/assets/images/covers');
if (!fs.existsSync(coversDir)) {
  fs.mkdirSync(coversDir, { recursive: true });
}

const photoMap = {
  game_001: 'photo-1515694346937-94d85e41e6f0', // Cô Gái Bên Cửa Sổ (Rain on dark window pane)
  game_002: 'photo-1516589178581-6cd7833ae3b2', // Gương (Antique mirror reflection & candle)
  game_003: 'photo-1457369804613-52c61a468e7d', // Nguyệt Lạc OA (Detective mystery documents)
  game_004: 'photo-1518709268805-4e9042af9f23', // Sinh Tử (Skull & gothic life/death noir)
  game_005: 'photo-1587614382346-4ec70e388b28', // Lý Luận Hư Cấu (Vintage typewriter & crime script)
  game_006: 'photo-1509114397022-ed747cca3f65', // Ngôi Nhà Ngọt Ngào (Dark gothic haunted mansion midnight)
  game_007: 'photo-1518895949257-7621c3c786d7', // Yandere 1 (Dark crimson blood-red roses)
  game_008: 'photo-1586075010923-2dd4570fb338', // Yandere 2 (Wax-sealed letter & secret envelope)
  game_009: 'photo-1542601906990-b4d3fb778b09', // Tuyết Hương Liên Hoàn Án (Ancient Asian pagoda in snow)
  game_010: 'photo-1474487548417-781cb71495f3', // Lên Đường (Vintage steam train entering misty railway)
  game_011: 'photo-1509042239860-f550ce710b93', // 1 Giờ 30 (Antique pocket watch in midnight shadows)
  game_012: 'photo-1528728329032-2972f65dfb3f', // Giày Thêu Hoa (Asian antique palace & red lanterns)
  game_013: 'photo-1558494949-ef010cbdcc31', // 2/12 (Heavy steel bank vault / combination safe)
  game_014: 'photo-1580582932707-520aed937b7b', // Điểm Danh (Dark empty school classroom)
  game_015: 'photo-1518770660439-4636190af475', // Tần Số Cuối (Electronic frequency circuit motherboard)
  game_016: 'photo-1518457607834-6e8d80c183c5', // Vòng Lặp Mưu Sát Đảo Mèo (Stormy ocean rocky island cliffs)
  game_017: 'photo-1519494026892-80bbd2d6fd0d', // Bệnh Viện Phụ Sản Ái Âu (Creepy abandoned hospital corridor)
  game_018: 'photo-1534447677768-be436bb09401', // Cậu Bé Váy Đỏ (Haunting eerie attic shadows)
  game_019: 'photo-1579783900882-c0d3dad7b119', // Điều Dân (Traditional Chinese ink & scroll)
  game_020: 'photo-1507676184212-d03ab07a01bf', // Tội Diễn Sư (Dramatic theater stage spotlight)
  game_021: 'photo-1511193311914-0346f16efe90', // Kiếm Tiền (Casino poker chips & cards)
  game_022: 'photo-1534536281715-e28d76689b4d', // Cuộc Gọi Đến (Vintage black rotary telephone)
  game_023: 'photo-1514362545857-3bc16c4c7d1b', // Thiên Hạ Đệ Nhất Tửu (Ancient tavern drinks & jars)
  game_024: 'photo-1515934751635-c81c6bc9a2d8', // Ly Hôn Đi! Nhanh Lên! (Courtroom wedding rings & papers)
  game_025: 'photo-1527661591475-527312dd65f5', // 100 Cách Chết NYC (Poison apothecary vials)
  game_026: 'photo-1508739773434-c26b3d09e071', // Thông Báo Tìm Người (Misty mountain night search)
  game_027: 'photo-1508615039623-a25605d2b022', // Thược Dược Đen (Dark gothic dahlia flower)
  game_028: 'photo-1513836279014-a89f7a76ae86', // Niên Luân (Concentric tree rings cross-section)
  game_029: 'photo-1448375240586-882707db888b', // Vụ Nhà Quán (Foggy cabin in deep pine forest)
  game_030: 'photo-1508700115892-45ecd05ae2ad', // Yandere 3 (Dramatic shattered glass & shadows)
  game_031: 'photo-1516483638261-f4dbaf036963', // Thanh Lâu (Ancient Asian ornate carved pavilion)
  game_032: 'photo-1508804185872-d7badad00f7d', // Hậu Cung (Forbidden City imperial golden roofs)
  game_033: 'photo-1518241353330-0f7941c2d9b5', // Thanh Thanh Man (Misty emerald bamboo grove)
  game_034: 'photo-1493976040374-85c8e12f0c0e', // Khách Điếm Hồ Ly (Mystical mountain tavern lanterns at dusk)
  game_035: 'photo-1492571350019-22de08371fd3', // Lý Xuyên Quái Đàm Bộ (Tokyo dark rainy neon alley)
  game_036: 'photo-1503899036084-c55cdd92da26', // Đền Thần Shimizu Hachiman (Japanese Shinto torii gate)
  game_037: 'photo-1476820865390-c52aeebb9891', // Xin Chào (Vintage nostalgic road & autumn memories)
  game_038: 'photo-1511671782779-c97d3d27a1d4', // Thi Khúc Li Biệt (Vintage violin & sheet music)
  game_039: 'photo-1523293182086-7651a899d37f', // Dư Hương (Antique glass perfume & dried petals)
  game_040: 'photo-1447752875215-b2761acb3c5d', // Cổ Thụ Ngân Nga (Enchanted giant ancient tree)
  game_041: 'local_martinez',                    // Martinez Chết Tại Kinh Kỳ Quán (Preserved baseline)
  game_042: 'photo-1513694203232-719a280e022f'  // Lập Phương Quán (Mysterious locked puzzle chamber)
};

console.log(`Starting targeted download of 42 high-resolution thematic covers to ${coversDir}...`);

let successCount = 0;
let failCount = 0;

for (const [id, photoId] of Object.entries(photoMap)) {
  const filePath = path.join(coversDir, `${id}.jpg`);

  if (photoId === 'local_martinez') {
    const srcPath = path.resolve('frontend/assets/images/martinez_mountain_inn.jpg');
    if (fs.existsSync(srcPath)) {
      fs.copyFileSync(srcPath, filePath);
      console.log(`[COPIED] ${id}.jpg from martinez_mountain_inn.jpg`);
      successCount++;
    } else {
      console.error(`[FAIL] ${id}: martinez_mountain_inn.jpg not found`);
      failCount++;
    }
    continue;
  }

  const url = `https://images.unsplash.com/${photoId}?auto=format&fit=crop&w=720&q=82`;
  try {
    const res = await fetch(url);
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    const buf = Buffer.from(await res.arrayBuffer());
    fs.writeFileSync(filePath, buf);
    console.log(`[DOWNLOADED] ${id}.jpg (${buf.length} bytes)`);
    successCount++;
  } catch (err) {
    console.error(`[ERROR] ${id}: ${err.message}`);
    failCount++;
  }
}

console.log(`\n========================================`);
console.log(`Summary: ${successCount} successful, ${failCount} failed.`);
console.log(`========================================`);
