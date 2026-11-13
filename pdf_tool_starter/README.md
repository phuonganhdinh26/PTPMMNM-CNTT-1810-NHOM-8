# PDF Studio - Đề tài 12

Ứng dụng PHP + MySQL mô phỏng công cụ xử lý PDF trực tuyến, giao diện lấy cảm hứng từ các dịch vụ như Smallpdf/iLovePDF.

## Chức năng có trong bản khởi đầu
- Đăng ký, đăng nhập, đăng xuất; mật khẩu được băm bằng `password_hash`.
- Tải nhiều PDF; kiểm tra phần mở rộng, chữ ký `%PDF-`, dung lượng và số trang do PDF.js đọc trên trình duyệt.
- Hiển thị thumbnail trang, chọn trang, kéo đổi thứ tự, xoay 90/180/270 độ, bỏ trang, hoàn tác.
- Ghép và tách trang đã chọn thành nhóm đã chọn/phần còn lại.
- Lưu kế hoạch chỉnh sửa và phiên bản; tạo snapshot riêng cho từng lần xuất.
- Lưu output và bản đồ trang nguồn; kiểm soát truy cập theo `user_id`; tệp gốc không bị ghi đè.
- Giao diện responsive tiếng Việt.

## Cài đặt trên XAMPP
1. Sao chép thư mục `pdf_tool_starter` vào `C:\xampp\htdocs\`.
2. Mở XAMPP Control Panel, bật **Apache** và **MySQL**.
3. Vào `http://localhost/phpmyadmin`, chọn tab **Import**, nhập file `schema.sql` trong thư mục dự án.
4. Mở `config.php`, kiểm tra `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`. XAMPP mặc định thường là `root` và mật khẩu rỗng, nhưng hãy dùng cấu hình thực tế của máy bạn.
5. Cấp quyền ghi cho thư mục `storage`, nếu hệ thống yêu cầu.
6. Mở `http://localhost/pdf_tool_starter/`, tạo tài khoản rồi đăng nhập.

## Thư viện
- PDF.js 3.11.174 qua CDN để đọc và xem trước trang.
- pdf-lib 1.17.1 qua CDN để ghép/xuất PDF.
- Cần kết nối Internet để tải hai thư viện CDN. Không cần Composer.

## Giới hạn và lưu ý
- Bản này chỉ nhận PDF không đặt mật khẩu. Tệp hỏng có thể vượt qua kiểm tra chữ ký ban đầu nhưng sẽ bị PDF.js/pdf-lib từ chối khi đọc/xuất.
- Số trang được đọc ở trình duyệt rồi gửi cho PHP. Với triển khai thực tế, cần kiểm tra lại số trang phía máy chủ bằng parser PDF đáng tin cậy, giới hạn tốc độ và tổng dung lượng theo tài khoản.
- Xuất PDF được thực hiện bằng pdf-lib trên trình duyệt, sau đó PHP lưu tệp, snapshot và lịch sử công việc. Đây là cách giữ nguyên mapping trang rõ ràng trong bản demo; nếu đề bài yêu cầu toàn bộ thao tác PDF chạy ở server, hãy chuyển phần export sang thư viện PHP như FPDI/TCPDF hoặc dịch vụ worker riêng.
- Phần tách hiện xuất hai nhóm: trang đã chọn và phần còn lại. Không hỗ trợ tự nhập nhiều biểu thức nhóm tùy ý trong bản khởi đầu.
- Tệp lưu trong `storage` và có `.htaccess` chặn truy cập trực tiếp trên Apache. Không dùng server production nếu chưa cấu hình HTTPS, CSRF token, quét tệp, quota, logging và chính sách xóa dữ liệu.
- Kết quả phụ thuộc phiên bản PDF.js/pdf-lib. Hãy chạy các phép thử của đề bài với bộ PDF có nhãn riêng trước khi nộp.

## Cấu trúc dữ liệu
- `documents`: thông tin tệp nguồn.
- `source_pages`: từng trang nguồn.
- `edit_plans`: kế hoạch và phiên bản.
- `export_jobs`: trạng thái từng lần xuất, có `plan_snapshot` độc lập.
- `outputs`: tệp kết quả và `page_map`.

## Kiểm tra nhanh
1. Tạo hai PDF thử có nhãn trang duy nhất.
2. Tải lên, chọn trang A2, B1, A1 và xuất. Mở PDF kết quả, đối chiếu đúng 3 trang.
3. Chọn một trang xoay 90°, bỏ trang khác rồi xuất và kiểm tra số trang/kích thước hiển thị.
4. Chọn hai trang rồi dùng **Tách trang đã chọn**; kiểm tra tệp nhóm chọn và tệp phần còn lại.
5. Tạo hai tài khoản, xác nhận mỗi tài khoản chỉ nhìn thấy tệp và output của chính mình.
