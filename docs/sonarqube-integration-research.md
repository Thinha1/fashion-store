# Nghiên cứu tích hợp SonarQube cho Fashion Store

## Kết luận

Dự án có thể tích hợp SonarQube mà không phải đổi kiến trúc ứng dụng hoặc thêm package Composer. Phương án phù hợp nhất là **SonarQube Cloud với GitHub Actions** vì repository `Thinha1/fashion-store` hiện là public, CI đã chạy trên GitHub Actions và gói OSS của SonarQube Cloud cho phép phân tích không giới hạn repository public, branch và pull request. [Subscription plans — SonarQube Cloud](https://docs.sonarsource.com/sonarqube-cloud/administering-sonarcloud/managing-subscription/subscription-plans)

Không nên đưa SonarQube Server vào `compose.yaml` của ứng dụng ở giai đoạn này. Community Build tự quản lý phù hợp cho học tập hoặc demo cục bộ, nhưng chỉ phân tích nhánh chính; phân tích branch và pull request cần deployment khác. Một máy chủ nhỏ cũng cần điểm khởi đầu khoảng 2 CPU, 4 GB RAM và 30 GB đĩa, ngoài tài nguyên của ứng dụng. [Analysis overview — Community Build](https://docs.sonarsource.com/sonarqube-community-build/analyzing-source-code/analysis-overview), [Server host requirements](https://docs.sonarsource.com/sonarqube-community-build/server-installation/server-host-requirements)

## Hiện trạng dự án

- Laravel 13, PHP 8.4, PHPUnit 12 và front-end dùng Vite.
- CI nằm tại `.github/workflows/ci.yml`, chạy Pint, build front-end, migration và PHPUnit trên MySQL 8.4.
- `actions/checkout@v4` chưa đặt `fetch-depth: 0`.
- `shivammathur/setup-php@v2` đang đặt `coverage: none`.
- PHP trong container hiện không có PCOV hoặc Xdebug.
- `phpunit.xml` đã giới hạn source coverage trong thư mục `app`.
- Mã cần phân tích chủ yếu là PHP/Blade, kèm CSS, JavaScript, Docker và GitHub Actions. SonarQube hỗ trợ PHP, Laravel, HTML, CSS, JavaScript, Docker và GitHub Actions; PHP 8.4 được hỗ trợ đầy đủ. [PHP language support](https://docs.sonarsource.com/sonarqube-cloud/advanced-setup/languages/php), [Supported languages](https://docs.sonarsource.com/sonarqube-community-build/analyzing-source-code/languages/overview)

Khoảng trống duy nhất để có chỉ số coverage là bộ tạo coverage. SonarQube không tự chạy test hoặc tự tạo coverage; scanner chỉ nhập báo cáo đã sinh. Với PHP, định dạng cần dùng là Clover XML qua `sonar.php.coverage.reportPaths`. [Test coverage parameters](https://docs.sonarsource.com/sonarqube-cloud/enriching/test-coverage/test-coverage-parameters)

## Thiết kế đề xuất

### 1. Tạo project trên SonarQube Cloud

Liên kết organization GitHub và import repository `Thinha1/fashion-store`. Chọn phân tích bằng GitHub Actions để CI có thể gửi cả kết quả phân tích và coverage. Tạo token, lưu vào GitHub Actions secret tên `SONAR_TOKEN`; không ghi token vào file cấu hình hoặc repository. SonarQube Cloud không cần `SONAR_HOST_URL`. [GitHub Actions setup](https://docs.sonarsource.com/sonarqube-cloud/advanced-setup/ci-based-analysis/github-actions-for-sonarcloud), [Official scan action](https://github.com/SonarSource/sonarqube-scan-action)

### 2. Thêm `sonar-project.properties`

Cấu hình khởi đầu:

```properties
sonar.organization=<organization-key>
sonar.projectKey=<project-key>
sonar.projectName=Fashion Store
sonar.sourceEncoding=UTF-8

sonar.sources=app,config,database,resources,routes,.github,.docker,compose.yaml
sonar.tests=tests
sonar.exclusions=resources/views/vendor/**,public/build/**

sonar.php.coverage.reportPaths=build/coverage/clover.xml
sonar.php.tests.reportPath=build/test-results/phpunit.xml
```

Không đưa `vendor`, `node_modules`, `storage` hoặc asset đã build vào `sonar.sources`, nên scanner sẽ không tính mã thư viện hay file sinh tự động. Nên giữ Blade trong phạm vi phân tích vì analyzer PHP hỗ trợ Laravel; nếu phát sinh cảnh báo sai do cú pháp template thì xử lý theo từng rule/file sau lần quét đầu, thay vì loại toàn bộ view.

`sonar.php.tests.reportPath` nhập kết quả chạy PHPUnit. SonarQube Cloud chỉ hiển thị test execution report trên branch, còn coverage report vẫn dùng được cho pull request. [Test execution parameters](https://docs.sonarsource.com/sonarqube-cloud/enriching/test-coverage/test-execution-parameters)

### 3. Điều chỉnh GitHub Actions

Các thay đổi cần thực hiện trong job `test` hiện tại:

```yaml
- name: Checkout
  uses: actions/checkout@v4
  with:
    fetch-depth: 0

- name: Setup PHP ${{ matrix.php }}
  uses: shivammathur/setup-php@v2
  with:
    php-version: ${{ matrix.php }}
    extensions: bcmath, exif, gd, intl, mbstring, pcntl, pdo_mysql, zip
    coverage: pcov
    tools: composer:v2

- name: PHPUnit with coverage
  run: |
    mkdir -p build/coverage build/test-results
    vendor/bin/phpunit \
      --coverage-clover=build/coverage/clover.xml \
      --log-junit=build/test-results/phpunit.xml

- name: SonarQube Scan
  uses: SonarSource/sonarqube-scan-action@v8.2.1
  env:
    SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
```

Full Git history giúp SonarQube gán issue, blame và new code chính xác hơn. Bản phát hành mới nhất đã kiểm tra tại thời điểm nghiên cứu là `sonarqube-scan-action` v8.2.1; khi triển khai có thể pin immutable commit SHA của release để tăng tính ổn định chuỗi cung ứng. [Official scan action](https://github.com/SonarSource/sonarqube-scan-action), [Scan action v8.2.1](https://github.com/SonarSource/sonarqube-scan-action/releases/tag/v8.2.1)

Lệnh trên dùng trực tiếp PHPUnit vì các cờ `--coverage-clover` và `--log-junit` đã được xác minh trên PHPUnit 12.5.34 của dự án. Không cần bật coverage trong Docker development nếu chỉ thu thập trên CI. Nếu đội cần chạy coverage thường xuyên ở local, có thể bổ sung PCOV vào `.docker/php/Dockerfile` ở một thay đổi riêng.

### 4. Quality Gate

Lần quét đầu nên dùng để tạo baseline và xem các cảnh báo thật của dự án. Sau khi phân loại cảnh báo, cấu hình Quality Gate tập trung vào **New Code**, rồi đặt SonarQube check thành required status check cho pull request. Cách này ngăn mã mới làm chất lượng giảm mà không buộc nhóm sửa toàn bộ nợ kỹ thuật cũ ngay trong một PR. [Quality standards and new code](https://docs.sonarsource.com/sonarqube-cloud/standards/about-new-code), [GitHub quality gate check](https://docs.sonarsource.com/sonarqube-cloud/managing-your-projects/administering-your-projects/devops-platform-integration/github)

Không nên dùng ngưỡng coverage tùy ý trước khi có baseline. Sau lần quét đầu, có thể đặt mục tiêu theo dữ liệu thật, ví dụ coverage của new code, duplicated lines, reliability và security rating.

## Phương án tự quản lý nếu bắt buộc dùng SonarQube Server

Tạo file Compose riêng như `compose.sonar.yaml`, gồm SonarQube Community Build và PostgreSQL; không dùng H2 cho production. Tài liệu chính thức khuyến nghị database production nằm trên máy riêng có độ trễ thấp, và PostgreSQL phải dùng UTF-8. [Installing the database](https://docs.sonarsource.com/sonarqube-community-build/server-installation/installing-the-database)

GitHub-hosted runner phải truy cập được URL của server. Một SonarQube container chỉ chạy trên máy cá nhân tại `localhost:9000` sẽ không nhận được kết quả từ GitHub Actions. Khi tự quản lý, cần thêm:

- GitHub variable `SONAR_HOST_URL` trỏ tới server có HTTPS.
- GitHub secret `SONAR_TOKEN`.
- Backup PostgreSQL, cập nhật phiên bản, TLS và theo dõi tài nguyên.
- Cấu hình Linux/Elasticsearch như `vm.max_map_count`, file descriptor và thread limit theo tài liệu chính thức. [Linux prerequisites](https://docs.sonarsource.com/sonarqube-community-build/server-installation/pre-installation/linux)

Community Build có thể chạy nhanh để thử nghiệm bằng Docker, nhưng image thử nghiệm và H2 không phải cấu hình production. [Try Community Build](https://docs.sonarsource.com/sonarqube-community-build/try-out-sonarqube)

## Trình tự triển khai

1. Import repository public vào SonarQube Cloud và tạo `SONAR_TOKEN`.
2. Thêm `sonar-project.properties` bằng key thật do SonarQube cấp.
3. Sửa CI để bật PCOV, sinh Clover/JUnit, quét bằng action và dùng full Git history.
4. Chạy CI trên một branch thử nghiệm; xác nhận test, coverage và scan đều hoàn tất.
5. Xem baseline, xử lý cảnh báo sai và điều chỉnh phạm vi phân tích nếu cần.
6. Cấu hình Quality Gate cho New Code và đặt check thành required sau khi baseline ổn định.

## Rủi ro cần lưu ý

- Coverage làm bước PHPUnit chậm hơn; mức tăng phải đo trên CI thực tế.
- Pull request từ fork không nhận repository secrets theo cơ chế bảo mật của GitHub, nên bước scan cần điều kiện phù hợp nếu dự án nhận đóng góp từ fork. [Using secrets in GitHub Actions](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets)
- File Blade có thể cần tinh chỉnh rule hoặc issue exclusion sau lần quét đầu.
- Nếu repository chuyển thành private, phải kiểm tra lại giới hạn LOC và gói SonarQube Cloud đang dùng.
- Tránh bật đồng thời Automatic Analysis và CI-based Analysis cho cùng project; phương án này chọn CI-based Analysis để nhập được coverage. Automatic Analysis không hỗ trợ coverage và sẽ xung đột với scanner trong CI. [Automatic analysis](https://docs.sonarsource.com/sonarqube-cloud/advanced-setup/automatic-analysis)

## Ước lượng

Phần thay đổi trong repository nhỏ: một file cấu hình và khoảng ba thay đổi trong workflow. Thời gian kỹ thuật thường dưới nửa ngày, chưa tính thời gian đăng nhập/liên kết organization, tạo token và xử lý baseline. Việc vận hành SonarQube Server tự quản lý tốn thêm thời gian triển khai, bảo mật, backup và nâng cấp định kỳ.
