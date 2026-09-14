# IMC 社務管理中心 2.0.0

可直接安裝於 WordPress 6.2+、PHP 7.4+。這是一個安裝在既有 WordPress 的外掛，不是網站、佈景主題或託管服務。外掛將 IMC 活動、行事曆、站內報名、候補、簽到、社員、入社申請、公告及社務資料庫集中管理。

## 已完成

- 活動管理：時間、地點、名額、截止日、費用、窗口、活動分類
- 報名系統：重複報名防護、剩餘名額、候補、個資同意、通知信、報名代碼
- 報名後台：確認／簽到／取消、依活動篩選、UTF-8 CSV 匯出
- 行事曆：響應式月曆、月份切換、當月活動摘要
- 近期活動卡片與公開 REST API：`/wp-json/imc/v1/events`
- iCalendar 產生器：供 Google／Apple／Outlook Calendar 加入活動
- 入社申請表與後台名單
- 社員名冊：社員編號、公司、職稱、屆次、社務職務、次團、狀態及 CSV 匯出
- 現場簽到：輸入或掃描報名代碼查詢並確認簽到
- 公告管理與公告短代碼
- 工具與技能控制台：檢查資料表、資料類型、時區與通知信箱
- IMC 資料庫：可管理章程、社刊、講師、歷屆社長、理監事及下載文件
- 預載 `data/yunlin-imc-seed.json`，保存本次從官網整理的基礎資料
- 安全處理：Nonce、權限檢查、輸入清理、輸出跳脫、資料表前綴

## 安裝

1. WordPress 後台 → 外掛 → 安裝外掛 → 上傳外掛。
2. 選擇 `imc-club-manager.zip` 並啟用。
3. 到「IMC 設定」確認社名、聯絡信箱、電話、地址與個資文字。
4. 到「IMC 活動」建立第一場活動。

## Render 部署

此 repository 已包含 Render Blueprint：

- `Dockerfile`：以官方 WordPress Docker image 為底，預裝 `imc-club-manager` 外掛。
- `render.yaml`：建立 WordPress Web Service、MySQL Private Service，以及兩個 persistent disks。

部署後請開啟 Render 提供的 `.onrender.com` URL 完成 WordPress 安裝精靈，再到 WordPress 後台啟用「IMC 社務管理中心」外掛。

## 短代碼

- `[imc_calendar]`：月曆
- `[imc_event_list limit="10"]`：近期活動
- `[imc_registration id="123"]`：指定活動報名表
- `[imc_join_form]`：入社申請
- `[imc_member_directory]`：登入後可見的社員名錄（預設不公開聯絡資料）
- `[imc_member_directory public="1"]`：公開姓名、公司、職稱及社務職務
- `[imc_notices limit="5"]`：最新公告

## 需有第三方憑證才可啟用的技能

- LINE Login 與 LINE 官方帳號推播
- QR Code 報到掃描、桌次／接駁／住宿分組
- 金流及自動對帳（需確認金流商與會計流程）
- 會員登入、眷屬名冊、會費與權限分級
- Google Calendar 雙向同步（需 Google OAuth 憑證）
- 電子社刊、相簿、文件權限與屆次交接封存

## 重要提醒

啟用外掛不會建立新網站、不會發布網站，也不會自動公開官網內容或連接 Google、LINE、金流。正式上線前請先在測試站驗證郵件、時區、個資告知、角色權限及備份政策。
