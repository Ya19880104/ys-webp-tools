# Changelog

本外掛所有重要變更皆記錄於此。

格式遵循 [Keep a Changelog](https://keepachangelog.com/zh-TW/1.0.0/)，版本號遵循 [語意化版本](https://semver.org/lang/zh-TW/)。

## [1.0.0] - 2026-06-16

### 新增
- **WebP 轉換**：上傳時自動將 JPG/PNG 轉為 WebP，預設取代原檔，可選擇保留原始檔；品質（1–100）與來源格式（JPEG/PNG/GIF）可設定；含記憶體保護避免大圖造成 OOM。
- **自動縮圖**：上傳超過最大寬/高的圖片時自動等比例縮小並覆寫原圖。
- **縮圖尺寸管理**：自動偵測所有已註冊的縮圖尺寸（含主題／外掛動態尺寸與 WP 大圖 `-scaled` 門檻），可逐項停用。
- 頁籤式後台設定頁（莫蘭迪色系），設定以 AJAX（JSON）儲存至自訂資料表。
- 電商工具箱選單整合與 YS Plugin Hub Client 自動更新。
