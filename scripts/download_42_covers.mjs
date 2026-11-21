import fs from 'node:fs';
import path from 'node:path';

const coversDir = path.resolve('frontend/assets/images/covers');
if (!fs.existsSync(coversDir)) {
  fs.mkdirSync(coversDir, { recursive: true });
}

const photoMap = {
  game_001: 'photo-1515694346937-94d85e41e6f0', // Cô Gái Bên Cửa Sổ (Rain on dark window pane)
  game_002: 'photo-1544717305-2782549b5136', // Gương (Moody shadow & antique reflection)
  game_003: 'photo-1457369804613-52c61a468e7d', // Nguyệt Lạc OA (Detective mystery documents)
  game_004: 'photo-1518709268805-4e9042af9f23', // Sinh Tử (Skull & gothic life/death noir)
  game_005: 'photo-1516962215378-7fa2e137ae93', // Lý Luận Hư Cấu (Vintage crime typewriter)
  game_006: 'photo-1518780664697-55e3ad937233', // Ngôi Nhà Ngọt Ngào (Dark Victorian house at midnight)
  game_007: 'photo-1518895949257-7621c3c786d7', // Yandere 1 (Dark crimson blood-red roses)
  game_008: 'photo-1586075010923-2dd4570fb338', // Yandere 2 (Wax-sealed letter & tragic secret)
  game_009: 'photo-1542601906990-b4d3fb778b09', // Tuyết Hương Liên Hoàn Án (Ancient Asian palace in snow)
  game_010: 'photo-1474487548417-781cb71495f3', // Lên Đường (Vintage steam train in fog)
  game_011: 'photo-1509042239860-f550ce710b93', // 1 Giờ 30 (Antique watch & midnight shadows)
  game_012: 'photo-1528728329032-2972f65dfb3f', // Giày Thêu Hoa (Asian antique palace & lanterns)
  game_013: 'photo-1558494949-ef010cbdcc31', // 2/12 (Vault / iron combination safe)
  game_014: 'photo-1580582932707-520aed937b7b', // Điểm Danh (Dark empty school classroom)
  game_015: 'photo-1518770660439-4636190af475', // Tần Số Cuối (Electronic waveform & circuit)
  game_016: 'photo-1507525428034-b723cf961d3e', // Vòng Lặp Mưu Sát Đảo Mèo (Stormy island coast)
  game_017: 'photo-1519494026892-80bbd2d6fd0d', // Bệnh Viện Phụ Sản Ái Âu (Creepy hospital corridor)
  game_018: 'photo-1534447677768-be436bb09401', // Cậu Bé Váy Đỏ (Haunting attic & antique shadows)
  game_019: 'photo-1579783900882-c0d3dad7b119', // Điều Dân (Traditional Chinese ink & scroll)
  game_020: 'photo-1507676184212-d03ab07a01bf', // Tội Diễn Sư (Dramatic theater stage spotlight)
  game_021: 'photo-1511193311914-0346f16efe90', // Kiếm Tiền (Casino chips & cards)
  game_022: 'photo-1534536281715-e28d76689b4d', // Cuộc Gọi Đến (Vintage red telephone in shadows)
  game_023: 'photo-1514362545857-3bc16c4c7d1b', // Thiên Hạ Đệ Nhất Tửu (Ancient drinks & sword rain)
  game_024: 'photo-1515934751635-c81c6bc9a2d8', // Ly Hôn Đi! Nhanh Lên! (Courtroom rings & table)
  game_025: 'photo-1527661591475-527312dd65f5', // 100 Cách Chết NYC (Poison bottle apothecary)
  game_026: 'photo-1508739773434-c26b3d09e071', // Thông Báo Tìm Người (Misty mountain night search)
  game_027: 'photo-1508615039623-a25605d2b022', // Thược Dược Đen (Black flowers noir)
  game_028: 'photo-1513836279014-a89f7a76ae86', // Niên Luân (Cross-section tree rings)
  game_029: 'photo-1448375240586-882707db888b', // Vụ Nhà Quán (Foggy cabin in deep forest)
  game_030: 'photo-1508700115892-45ecd05ae2ad', // Yandere 3 (Dramatic shattered glass & shadows)
  game_031: 'photo-1516483638261-f4dbaf036963', // Thanh Lâu (Ancient Asian ornate architecture)
  game_032: 'photo-1535713875002-d1d0cf377fde', // Hậu Cung (Imperial golden court portrait)
  game_033: 'photo-1518241353330-0f7941c2d9b5', // Thanh Thanh Man (Misty bamboo grove)
  game_034: 'photo-1493976040374-85c8e12f0c0e', // Khách Điếm Hồ Ly (Mystical mountain tavern lanterns)
  game_035: 'photo-1492571350019-22de08371fd3', // Lý Xuyên Quái Đàm Bộ (Tokyo dark alley noir)
  game_036: 'photo-1503899036084-c55cdd92da26', // Đền Thần Shimizu Hachiman (Japanese Shinto shrine)
  game_037: 'photo-1476820865390-c52aeebb9891', // Xin Chào (Vintage nostalgic road & memories)
  game_038: 'photo-1511671782779-c97d3d27a1d4', // Thi Khúc Li Biệt (Vintage violin & sheet music)
  game_039: 'photo-1523293182086-7651a899d37f', // Dư Hương (Antique glass perfume & dried petals)
  game_040: 'photo-1447752875215-b2761acb3c5d', // Cổ Thụ Ngân Nga (Enchanted giant ancient tree)
  game_041: 'local_martinez',                    // Martinez Chết Tại Kinh Kỳ Quán (Preserved baseline)
  game_042: 'photo-1513694203232-719a280e022f'  // Lập Phương Quán (Mysterious puzzle chamber)
};

console.log(`Starting download/verification of 42 high-resolution photographic covers to ${coversDir}...`);

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
