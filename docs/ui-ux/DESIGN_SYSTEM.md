# TÀI LIỆU HỆ THỐNG THIẾT KẾ (DESIGN SYSTEM)
## KỊCH BẢN SÁT V16 — CINEMATIC MODERN HYBRID

---

## 1. TRIẾT LÝ THIẾT KẾ: CINEMATIC MODERN HYBRID

Hệ thống thiết kế của **Kịch Bản Sát V16** được xây dựng trên sự giao thoa hài hòa giữa hai phong cách:
1. **Cinematic (Bản sắc Trinh thám & Bí ẩn)**:
   - Tông màu đen sâu thẳm (Void Black `#0B0C10`), sắc đỏ nhung án mạng (Crimson `#C43737`), vàng ánh nến (Aged Gold `#D4AF37`).
   - Sử dụng phông chữ tiêu đề có chân cổ điển (*Playfair Display*) gợi cảm giác như đang lật giở một hồ sơ mật trong dinh thự cổ.
   - Hiệu ứng ánh sáng tinh tế, bóng đổ mềm sâu, tạo chiều sâu thị giác.
2. **Modern & Functional (Hiện đại & Chuyên nghiệp)**:
   - Cấu trúc lưới linh hoạt, phân cấp thông tin rõ ràng, tối ưu tốc độ thao tác.
   - Sử dụng phông chữ nội dung không chân hiện đại (*Be Vietnam Pro*) với khả năng đọc tối ưu cho tiếng Việt có dấu.
   - Hệ thống thẻ, bảng dữ liệu, thanh tiến trình trực quan theo chuẩn ứng dụng Web thời gian thực.

---

## 2. BẢNG MÀU CHUẨN (COLOR PALETTE TOKENS)

Hệ thống màu sắc được định nghĩa tập trung tại `frontend/src/styles/design-tokens.css`:

### 2.1. Nền trung tính (Neutral Surfaces - Dark First)
| Tên biến CSS | Mã Hex | Tỷ lệ tương phản | Ứng dụng |
| :--- | :--- | :--- | :--- |
| `--kbs-bg-void` | `#0B0C10` | Nền gốc | Nền toàn trang, độ sâu tối đa |
| `--kbs-bg-surface` | `#13151F` | $\approx 15:1$ vs Text | Khối Section, thanh điều hướng Header |
| `--kbs-bg-card` | `#1A1D27` | $\approx 12:1$ vs Text | Thẻ kịch bản, phòng chơi, biểu mẫu |
| `--kbs-bg-card-hover` | `#222634` | — | Trạng thái di chuột trên thẻ |
| `--kbs-bg-elevated` | `#292F42` | — | Menu thả xuống, Slide-over Drawer, Hộp thoại |

### 2.2. Màu chữ & Tương phản (Typography Hierarchy)
| Tên biến CSS | Mã Hex | Tỷ lệ tương phản WCAG | Mục đích sử dụng |
| :--- | :--- | :--- | :--- |
| `--kbs-text-primary` | `#F8FAFC` | $> 14:1$ (Đạt AAA) | Tiêu đề chính, văn bản quan trọng |
| `--kbs-text-secondary` | `#CBD5E1` | $> 9:1$ (Đạt AAA) | Nội dung mô tả, phụ đề, nhãn biểu mẫu |
| `--kbs-text-muted` | `#94A3B8` | $> 5.8:1$ (Đạt AA) | Chú thích nhỏ, thời gian, số đếm |
| `--kbs-text-dim` | `#64748B` | $> 4.5:1$ (Đạt AA) | Chữ vô hiệu hóa, watermark |

### 2.3. Màu điểm nhấn thương hiệu (Brand Accents)
| Tên biến CSS | Mã Hex | Ý nghĩa & Vị trí ứng dụng |
| :--- | :--- | :--- |
| `--kbs-gold` | `#D4AF37` | Vàng hoàng gia: Nút hành động chính, huy hiệu nổi bật, giá vé |
| `--kbs-gold-hover` | `#FBBF24` | Vàng sáng khi hover vào nút chính |
| `--kbs-crimson` | `#C43737` | Đỏ nhung trinh thám: Nhãn 18+, cảnh báo án mạng, hủy phòng |
| `--kbs-crimson-hover`| `#EF4444` | Đỏ sáng khi hover |

### 2.4. Bảng màu định danh theo 4 Portal (Portal Themes)
- **Public Portal**: Nền huyền bí `#0B0909`, điểm nhấn Vàng kim `#D4AF37`.
- **Player Portal**: Xanh điện tử (Electric Blue `#3B82F6`) tạo cảm giác phòng chờ game hiện đại (Matchmaking Lounge).
- **DM Portal**: Cam hổ phách (Tactical Amber `#F59E0B`) tạo cảm giác bàn điều khiển chiến thuật (Tactical Command Desk).
- **Admin Portal**: Xanh tím điều hành (Executive Indigo `#6366F1`) tạo cảm giác bảng điều khiển doanh nghiệp (Enterprise Console).

---

## 3. HỆ THỐNG TYPOGRAPHY

### 3.1. Phông chữ (Font Families)
- **Display Font**: `'Playfair Display', Georgia, serif` — Dành cho các tiêu đề chính `h1`, `h2`, thẻ kịch bản lớn mang tính điện ảnh.
- **Body Font**: `'Be Vietnam Pro', system-ui, sans-serif` — Dành cho toàn bộ nội dung đọc, bảng dữ liệu, nhãn điều hướng, tối ưu dấu tiếng Việt.
- **Mono Font**: `'JetBrains Mono', 'Fira Code', monospace` — Dành cho mã phòng `#KBS-104`, mã vé, đồng hồ đếm ngược, ID giao dịch.

### 3.2. Thang kích thước chữ (Type Scale)
| Tên biến | Kích thước | Line Height | Ứng dụng |
| :--- | :--- | :--- | :--- |
| `--kbs-text-xs` | `0.75rem` (12px) | 1.4 | Nhãn badge, thời gian phụ |
| `--kbs-text-sm` | `0.875rem` (14px) | 1.5 | Chữ trong bảng, nút phụ, chú thích |
| `--kbs-text-base` | `1rem` (16px) | 1.6 | Văn bản thân bài, trường nhập liệu |
| `--kbs-text-lg` | `1.125rem` (18px) | 1.5 | Tiêu đề card con, điểm nhấn giá vé |
| `--kbs-text-xl` | `1.25rem` (20px) | 1.4 | Tiêu đề khối Section nhỏ |
| `--kbs-text-2xl` | `1.5rem` (24px) | 1.3 | Tiêu đề trang Dashboard |
| `--kbs-text-3xl` | `1.875rem` (30px) | 1.2 | Tiêu đề Hero trang chủ |
| `--kbs-text-4xl` | `2.25rem` (36px) | 1.15 | Tiêu đề Cinematic chính |

---

## 4. QUY CHUẨN KHOẢNG CÁCH (SPACING SYSTEM - 4PX/8PX SCALE)

Áp dụng nghiêm ngặt thang đo 4px / 8px:
- `4px` (`--kbs-space-1`): Khoảng cách giữa icon và chữ.
- `8px` (`--kbs-space-2`): Đệm badge, khoảng cách giữa các trường nhỏ.
- `12px` (`--kbs-space-3`): Đệm nút bấm nhỏ, khoảng cách nhóm nút.
- `16px` (`--kbs-space-4`): Đệm thẻ con, khoảng cách dòng.
- `20px` (`--kbs-space-5`): Đệm card chuẩn, khoảng cách khối dữ liệu.
- `24px` (`--kbs-space-6`): Khoảng cách giữa các cột trong lưới (`gap`).
- `32px` (`--kbs-space-8`): Khoảng cách giữa các Section trên desktop.
- `48px` (`--kbs-space-12`): Khoảng cách đỉnh trang và chân trang.

---

## 5. BO GÓC & BÓNG ĐỔ (RADII & ELEVATIONS)

### 5.1. Bo góc (Border Radii)
- `--kbs-radius-sm`: `4px` (Badge nhỏ, checkbox, viền chi tiết).
- `--kbs-radius-md`: `8px` (Thẻ kịch bản, ô nhập liệu form, nút bấm).
- `--kbs-radius-lg`: `12px` (Thẻ phòng chơi chính, bảng điều khiển, modal box).
- `--kbs-radius-xl`: `16px` (Khung sân khấu Parallax, Slide-over Drawer).
- `--kbs-radius-full`: `9999px` (Pill điều hướng, Avatar tròn, nút tròn).

### 5.2. Bóng đổ (Shadows)
- `--kbs-shadow-sm`: `0 1px 3px rgba(0, 0, 0, 0.4)`
- `--kbs-shadow-md`: `0 4px 14px rgba(0, 0, 0, 0.5)`
- `--kbs-shadow-lg`: `0 10px 25px -5px rgba(0, 0, 0, 0.6)`
- `--kbs-shadow-drawer`: `-4px 0 30px rgba(0, 0, 0, 0.7)` (Đổ bóng cho Slide-over Drawer từ mép phải).

---

## 6. TIÊU CHUẨN TIẾP CẬN & KHẢ DỤNG (ACCESSIBILITY & USABILITY)

1. **Tỷ lệ tương phản chuẩn WCAG AA**:
   - Tất cả văn bản hiển thị đạt tối thiểu `4.5:1` đối với chữ thường và `3:1` đối với chữ lớn/in đậm.
2. **Kích thước vùng bấm (Touch Target)**:
   - Mọi nút bấm tương tác trên thiết bị di động đều đạt diện tích tối thiểu $44 \times 44$px hoặc chiều cao padding đảm bảo bấm dễ dàng không bị nhầm lẫn.
3. **Điều hướng bằng bàn phím (Keyboard Accessibility)**:
   - Toàn bộ Dialog, Drawer và Modal đều có thể đóng bằng phím `Escape`.
   - Lớp phủ nền Backdrop bắt sự kiện bấm ra ngoài để đóng nhẹ nhàng.
4. **Huy hiệu trạng thái có biểu tượng bổ trợ (Non-color reliant)**:
   - Các trạng thái không phụ thuộc hoàn toàn vào màu sắc mà có chữ và icon đi kèm (ví dụ: `✓ Đã cọc 50k`, `⏳ Chờ thế chỗ`, `✕ Bị từ chối`).
