# 🤝 Hướng dẫn đóng góp (CONTRIBUTING)

Tài liệu này quy định quy trình làm việc nhóm cho bài tập lớn môn **Phát triển phần mềm mã nguồn mở**.
Mọi thành viên **bắt buộc** tuân theo để đảm bảo lịch sử commit sạch, dễ review và tránh xung đột.

---

## 📌 Nguyên tắc chung

- ❌ **KHÔNG push trực tiếp lên nhánh `main`.** Mọi thay đổi phải đi qua Pull Request.
- ✅ Mọi thay đổi đều phải được **review và approve** bởi ít nhất 1 thành viên khác trước khi merge.
- ✅ Mỗi Pull Request chỉ giải quyết **một tính năng hoặc một lỗi** duy nhất.
- ✅ Viết commit message rõ ràng, có ý nghĩa, theo chuẩn **Conventional Commits**.
- ✅ Đồng bộ nhánh `main` thường xuyên để hạn chế conflict.

---

## 🔄 Quy trình làm việc 7 bước

### Bước 1 — Đồng bộ `main` trước khi bắt đầu

Luôn cập nhật nhánh `main` mới nhất về máy trước khi tạo nhánh làm việc:

```bash
git checkout main
git pull origin main
```

### Bước 2 — Tạo branch riêng theo convention

Đặt tên branch theo mẫu: `<loại>/<mô-tả-ngắn>`.

| Loại branch | Mục đích | Ví dụ |
| ----------- | -------- | ----- |
| `feat/` | Phát triển tính năng mới | `feat/dang-nhap` |
| `fix/` | Sửa lỗi | `fix/loi-hien-thi-ngay` |
| `docs/` | Viết / cập nhật tài liệu | `docs/huong-dan-cai-dat` |

```bash
git checkout -b feat/ten-tinh-nang
```

### Bước 3 — Commit theo chuẩn Conventional Commits

Cú pháp: `<type>: <mô tả ngắn gọn>`

| `type` | Ý nghĩa |
| ------ | ------- |
| `feat` | Thêm tính năng mới |
| `fix` | Sửa lỗi |
| `docs` | Thay đổi tài liệu |
| `refactor` | Tái cấu trúc mã, không đổi hành vi |
| `test` | Thêm / sửa kiểm thử |
| `chore` | Việc lặt vặt: cấu hình, build, dependency... |

**Ví dụ commit message:**

| Commit message | Diễn giải |
| -------------- | --------- |
| `feat: them chuc nang dang nhap` | Thêm tính năng đăng nhập |
| `fix: sua loi khong luu duoc du lieu` | Sửa lỗi lưu dữ liệu |
| `docs: cap nhat huong dan cai dat` | Cập nhật tài liệu cài đặt |
| `refactor: tach ham xu ly thanh module rieng` | Tái cấu trúc code |
| `test: them test cho module xu ly` | Bổ sung kiểm thử |
| `chore: cap nhat file ci.yml` | Cập nhật cấu hình CI |

```bash
git add .
git commit -m "feat: mo ta ngan gon tinh nang"
```

### Bước 4 — Push branch lên GitHub

```bash
git push origin feat/ten-tinh-nang
```

### Bước 5 — Mở Pull Request vào `main`

- Vào GitHub, mở Pull Request từ branch của bạn vào nhánh `main`.
- Điền đầy đủ [template PR](.github/pull_request_template.md): mô tả thay đổi, liên kết issue (nếu có), loại thay đổi, checklist.
- Gắn reviewer là các thành viên còn lại.

### Bước 6 — Chờ review và approve

- Cần **ít nhất 1 thành viên khác** review và bấm **Approve**.
- Phản hồi mọi comment review; nếu sửa thì push thêm commit vào chính branch đó.
- ❌ **Không tự approve Pull Request của chính mình.**

### Bước 7 — Merge bằng Squash merge và xoá branch

- Sau khi được approve, tiến hành **Squash merge** để gộp tất cả commit thành 1 commit sạch trên `main`.
- Xoá branch sau khi merge (cả trên GitHub và local).

```bash
# Xoá branch local sau khi đã merge
git checkout main
git pull origin main
git branch -d feat/ten-tinh-nang
```

---

## 🔍 Quy tắc review

- Mỗi PR phải được **tối thiểu 1 thành viên khác** approve.
- **Không tự approve** PR do chính mình tạo.
- Reviewer kiểm tra: code chạy được, đúng mục tiêu, không có file rác/secret, tài liệu đã cập nhật.
- Comment review nên lịch sự, cụ thể, mang tính xây dựng.

## ✂️ Chia nhỏ Pull Request

- **1 PR = 1 tính năng hoặc 1 lỗi.** Tránh gộp nhiều thay đổi không liên quan vào cùng một PR.
- PR nhỏ giúp review nhanh, dễ phát hiện lỗi và giảm xung đột khi merge.

## 🧹 Quy tắc chung khi đóng góp

- Không commit file rác, file cá nhân, hoặc thông tin bí mật (`.env`, API key, mật khẩu...).
- Viết comment tiếng Việt cho các hàm public.
- Cập nhật tài liệu (`README.md`, docstring...) khi thay đổi hành vi.
- Tự kiểm thử trước khi mở PR.

---

Cảm ơn các bạn đã tuân thủ quy trình! 🎉
