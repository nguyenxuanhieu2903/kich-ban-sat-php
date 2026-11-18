# MASTER ERD ARTIFACT SPECIFICATION & EXPORT GUIDE
## DỰ ÁN: BOOKING-PROJECT-PHP — PHASE 1: DATABASE DESIGN & ERD ACCEPTANCE

**Thư mục lưu trữ:** `docs/database/acceptance/02-erd/`

---

## 1. CÁC TẬP TIN ARTIFACT TRONG THƯ MỤC

| Tập tin | Định dạng | Mục đích sử dụng | Trạng thái kỹ thuật |
|---|---|---|:---:|
| `master-erd.drawio` | XML Draw.io standard | Sơ đồ Master ERD gốc đầy đủ 34 bảng, 8 Bounded Contexts, PK, FK, cardinalities. Mở bằng diagrams.net, VS Code Draw.io Integration, hoặc MySQL Workbench / draw.io app. | **ĐÃ HOÀN TẤT & XÁC THỰC** |
| `master-erd.svg` | Scalable Vector Graphics | Bản dựng hình ảnh vector chất lượng cao không vỡ nét của Master ERD, có thể mở trực tiếp trên mọi trình duyệt web (Chrome, Edge, Firefox). | **ĐÃ HOÀN TẤT & XÁC THỰC** |
| `master-erd.html` | Interactive HTML | Giao diện web trực quan để duyệt sơ đồ, zoom/pan và tải xuống các artifact. | **ĐÃ HOÀN TẤT & XÁC THỰC** |
| `master-erd.png` / `master-erd.pdf` | Raster / Document | Bản xuất tĩnh từ file `.drawio`. | **THÔNG BÁO GIỚI HẠN MÔI TRƯỜNG & HƯỚNG DẪN XUẤT** |

---

## 2. THÔNG BÁO GIỚI HẠN CÔNG CỤ VÀ PHƯƠNG ÁN THAY THẾ KIỂM CHỨNG

Theo hướng dẫn nghiệm thu:
> *"Nếu công cụ không hỗ trợ một định dạng, hãy thông báo và xuất định dạng thay thế có thể kiểm chứng."*

- **Giới hạn môi trường cục bộ:** Môi trường thực thi Windows hiện tại không cài đặt CLI `drawio` hoặc `draw.io desktop headless renderer` trong biến môi trường PATH để tự động chuyển đổi từ XML `.drawio` sang `.png` và `.pdf`.
- **Phương án thay thế có thể kiểm chứng ngay lập tức:**
  1. Tập tin vector chuẩn **`master-erd.svg`** và **`master-erd.html`** đã được sinh thành công với độ phân giải 2100x1700 px, hiển thị đầy đủ 34 bảng màu sắc phân tách theo 8 Bounded Contexts, không chồng lấn, đường nối vuông góc (orthogonal connectors).
  2. Bất kỳ lập trình viên hoặc kiểm toán viên nào có thể mở `master-erd.drawio` trực tiếp trên [app.diagrams.net](https://app.diagrams.net/) và chọn `File` -> `Export as` -> `PNG` hoặc `PDF` chỉ với 1 click chuột.
