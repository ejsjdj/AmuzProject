# MES 조립공정 대시보드
Laravel + Vue 3로 구현한 조립공정 MES 대시보드 과제입니다. 제공된 mock-data.json을 기반으로 생산·품질·설비 데이터를 불러와, 조립 라인의 생산 현황을 한 화면에서 직관적으로 모니터링할 수 있도록 구성했습니다.

# 실행 방법

xampp Control Panel 접속

MYSQL start

Control Paner 우측에 shell 버튼 클릭

xampp shell 이 열리면 

다음 명령어를 순차적으로 입력
mysql -u root -p
비밀번호입력(비밀번호가없으면 그냥 엔터)
CREATE DATABASE mes_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

프로젝트 클론

mysql 비밀번호가 있으면 프로젝트의 .env 파일을 열어서 DB 설정 부분이 DB_PASSWORD={비밀번호}   

일반 CMD 접속

콘솔창으로 해당 프로젝트의 루트경로로 이동

다음 명령어 순차적으로 실행

php artisan migrate
php artisan mock:import

다음 명령어 실행 : php artisan serve

콘솔창을 하나 더 열어 프로젝트의 frontend 폴더로 이동 (frontend)는 루트경로에 있습니다.

다음 명령어 실행 : npm run dev

크롬으로 다음 주소로 접속합니다. http://localhost:5173/dashboard

상단 네비게이션 바를 통해 원하는 화면(생산, 품질, 설비 등)으로 바로 이동할 수 있습니다.

🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌🙌 실행에는 Node.js, npm, Composer, PHP 가 필요할 수 있습니다.
