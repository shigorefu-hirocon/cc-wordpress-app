const CERTIFICATE_SPREADSHEET_ID = "1AzvCp6NUZJ_xch5911sL7euOqy-Xi4wE4x18Bks0vqc";
const CERTIFICATE_FORM_RESPONSES_SHEET_NAME = "DB_証明書申請";
const CERTIFICATE_TEST_CAREER_CENTER_MAIL = "eduard@hsc.ac.jp";
const CERTIFICATE_PRODUCTION_CAREER_CENTER_MAIL = "syushoku@ucs-hiroshima.ac.jp";

function handleCertificateWebhook_(e) {
  try {
    const rowData = normalizeCertificateRowData_(parseCertificateRequestRowData_(e));
    const context = createCertificateContext_();
    const appendedRowNumber = appendCertificateWebhookRowToSheet_(context.formResponsesSheet, rowData);

    const mailReport = sendCertificateEmails_(
      rowData,
      context.schoolMailMap,
      context.teacherMailMap,
      context.careerCenterMail,
    );

    writeCertificateMailReportToSheet_(context.formResponsesSheet, appendedRowNumber, mailReport);

    return createCertificateJsonResponse_({
      ok: true,
      mailReport,
    });
  } catch (error) {
    console.error(error.stack || error.message || error);

    return createCertificateJsonResponse_({
      ok: false,
      message: error.message || String(error),
      stack: error.stack || "",
    });
  }
}

function parseCertificateRequestRowData_(e) {
  if (!e) {
    throw new Error("リクエストデータがありません。");
  }

  if (e.parameter && e.parameter.payload) {
    return JSON.parse(e.parameter.payload);
  }

  if (e.postData && e.postData.contents) {
    return JSON.parse(e.postData.contents);
  }

  throw new Error("payload がありません。");
}

function createCertificateContext_() {
  const ss = SpreadsheetApp.openById(CERTIFICATE_SPREADSHEET_ID);
  const formResponsesSheet = getOrCreateCertificateSheet_(ss, CERTIFICATE_FORM_RESPONSES_SHEET_NAME);
  const schoolMailAddressSheet = ss.getSheetByName("学校メールアドレス");
  const homeroomTeacherSheet = ss.getSheetByName("担任");

  return {
    ss,
    formResponsesSheet,
    schoolMailMap: buildCertificateSchoolMailMap_(schoolMailAddressSheet),
    teacherMailMap: buildCertificateTeacherMailMap_(homeroomTeacherSheet),
    careerCenterMail: getCertificateCareerCenterMail_(),
  };
}

function getCertificateCareerCenterMail_() {
  const useProductionMail = PropertiesService
    .getScriptProperties()
    .getProperty("USE_PRODUCTION_CAREER_CENTER_MAIL") === "true";

  return useProductionMail
    ? CERTIFICATE_PRODUCTION_CAREER_CENTER_MAIL
    : CERTIFICATE_TEST_CAREER_CENTER_MAIL;
}

function getOrCreateCertificateSheet_(ss, sheetName) {
  const sheet = ss.getSheetByName(sheetName);
  if (sheet) return sheet;

  return ss.insertSheet(sheetName);
}

function normalizeCertificateRowData_(rowData) {
  const normalized = {};

  Object.keys(rowData).forEach((key) => {
    const value = rowData[key];
    const normalizedKey = normalizeCertificateHeaderName_(key);
    normalized[normalizedKey] = Array.isArray(value) ? value.filter(Boolean).join("\n") : value;
  });

  normalized["申請種別"] = "証明書発行申請";
  normalized["送信日時"] = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyy年MM月dd日 HH時mm分");

  return normalized;
}

function appendCertificateWebhookRowToSheet_(sheet, rowData) {
  const headers = ensureCertificateHeaders_(sheet, [
    "送信日時",
    "申請種別",
    "学籍番号",
    "学校名",
    "コース名",
    "名前",
    "生年月日",
    "電話番号",
    "電話番号（数字のみ）",
    "メールアドレス",
    "担任",
    "使用目的",
    "就職用_成績証明書_枚数",
    "就職用_卒業見込書_枚数",
    "就職用_健康診断書_枚数",
    "その他_在学証明書_枚数",
    "その他_成績証明書_枚数",
    "その他_卒業見込書_枚数",
    "その他_卒業証明書_枚数",
    "求人番号",
    "受験先企業名",
    "担任名",
    "書類提出日",
    "使用目的、提出先など",
    "受取方法",
    "備考",
    "合計金額",
    "メール送信レポート",
  ]);

  const values = headers.map((header) => {
    if (!header) return "";
    return getCertificateValue_(rowData, header.toString().trim());
  });

  sheet.appendRow(values);
  return sheet.getLastRow();
}

function ensureCertificateHeaders_(sheet, requiredHeaders) {
  const lastColumn = Math.max(sheet.getLastColumn(), 1);
  const headers = sheet.getRange(1, 1, 1, lastColumn).getValues()[0];
  const normalizedHeaders = headers.map((header) => header ? header.toString().trim() : "");
  let changed = false;

  requiredHeaders.forEach((requiredHeader) => {
    if (normalizedHeaders.includes(requiredHeader)) return;

    normalizedHeaders.push(requiredHeader);
    changed = true;
  });

  if (changed) {
    sheet.getRange(1, 1, 1, normalizedHeaders.length).setValues([normalizedHeaders]);
  }

  return normalizedHeaders;
}

function writeCertificateMailReportToSheet_(sheet, rowNumber, mailReport) {
  if (!rowNumber) return;

  const headers = ensureCertificateHeaders_(sheet, ["メール送信レポート"]);
  const reportColumnIndex = headers.indexOf("メール送信レポート") + 1;

  if (reportColumnIndex < 1) return;

  sheet
    .getRange(rowNumber, reportColumnIndex)
    .setValue(JSON.stringify(mailReport, null, 2));
}

function sendCertificateEmails_(rowData, schoolMailMap, teacherMailMap, careerCenterMail) {
  return [
    sendCertificateSchoolEmail_(rowData, schoolMailMap),
    sendCertificateTeacherEmail_(rowData, teacherMailMap),
    sendCertificateCareerCenterEmail_(rowData, careerCenterMail),
    sendCertificateStudentEmail_(rowData),
  ];
}

function sendCertificateSchoolEmail_(rowData, schoolMailMap) {
  const schoolName = getCertificateValue_(rowData, "学校名");
  const to = schoolMailMap[schoolName];

  if (!to) {
    return createCertificateMailReportEntry_("学校", "", "skipped", "学校メールアドレスが未登録です。");
  }

  return sendCertificateEmail_(to, buildCertificateSubject_(rowData), buildCertificateStaffMailBody_(rowData), "学校");
}

function sendCertificateTeacherEmail_(rowData, teacherMailMap) {
  const teacherName = getCertificateValue_(rowData, "担任") || getCertificateValue_(rowData, "担任名");
  const to = teacherMailMap[normalizeCertificateTeacherName_(teacherName)];

  if (!to) {
    return createCertificateMailReportEntry_("担任", "", "skipped", "担任メールアドレスが未登録です。");
  }

  return sendCertificateEmail_(to, buildCertificateSubject_(rowData), buildCertificateStaffMailBody_(rowData), "担任");
}

function sendCertificateCareerCenterEmail_(rowData, careerCenterMail) {
  if (!careerCenterMail) {
    return createCertificateMailReportEntry_("就職センター", "", "skipped", "就職センターメールアドレスが未設定です。");
  }

  return sendCertificateEmail_(careerCenterMail, buildCertificateSubject_(rowData), buildCertificateStaffMailBody_(rowData), "就職センター");
}

function sendCertificateStudentEmail_(rowData) {
  const to = getCertificateValue_(rowData, "メールアドレス").toString().trim();

  if (!to) {
    return createCertificateMailReportEntry_("学生", "", "skipped", "学生メールアドレスが未入力です。");
  }

  return sendCertificateEmail_(to, "証明書申請を受け付けました", buildCertificateStudentMailBody_(rowData), "学生");
}

function sendCertificateEmail_(to, subject, body, recipientType) {
  try {
    GmailApp.sendEmail(
      to,
      subject,
      body,
      { name: "証明書申請システム" },
    );

    return createCertificateMailReportEntry_(recipientType, to, "sent", "");
  } catch (error) {
    return createCertificateMailReportEntry_(recipientType, to, "error", error.message);
  }
}

function buildCertificateSubject_(rowData) {
  return `証明書申請-${getCertificateValue_(rowData, "名前")}-${getCertificateValue_(rowData, "学籍番号")}`;
}

function buildCertificateStaffMailBody_(rowData) {
  return [
    getCertificateApplicationDateTime_(rowData),
    "",
    "",
    "",
    "書類の申し込みがありました。",
    "",
    buildCertificateMailBody_(rowData),
  ].join("\n");
}

function buildCertificateStudentMailBody_(rowData) {
  return [
    getCertificateApplicationDateTime_(rowData),
    "",
    "",
    "",
    "証明書申請を受け付けました。",
    "以下の内容で申込みメールを送信しました。",
    "",
    buildCertificateMailBody_(rowData),
    "",
    `申込みから２日後（${getCertificateReceiveDateText_()}）、在学校の事務局にて受取りができます。`,
    "受取りの際は、『学生証』を提示してください。",
    "発行手数料は、後日、口座振替にてお支払いください。",
    "※事務局受付時間：平日 8:30～17:00",
    "※受取日は自動計算です。土日祝日にあたる場合は、翌営業日にお越しください。",
  ].join("\n");
}

function getCertificateApplicationDateTime_(rowData) {
  return getCertificateValue_(rowData, "送信日時")
    || Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyy年MM月dd日 HH時mm分");
}

function buildCertificateMailBody_(rowData) {
  const purpose = getCertificateValue_(rowData, "使用目的");
  const lines = [
    `学籍番号 ${getCertificateValue_(rowData, "学籍番号")}`,
    `学科 ${getCertificateValue_(rowData, "コース名")}`,
    `氏名 ${getCertificateValue_(rowData, "名前")}`,
    `生年月日 ${formatCertificateJapaneseEraDate_(getCertificateValue_(rowData, "生年月日"))}`,
    `電話番号 ${getCertificatePhoneNumber_(rowData)}`,
    `メールアドレス ${getCertificateValue_(rowData, "メールアドレス")}`,
  ];

  if (purpose === "その他") {
    lines.push(
      `在学証明書 ${getCertificateQuantity_(rowData, "その他_在学証明書_枚数")}枚`,
      `成績証明書 ${getCertificateQuantity_(rowData, "その他_成績証明書_枚数")}枚`,
      `卒業見込書 ${getCertificateQuantity_(rowData, "その他_卒業見込書_枚数")}枚`,
      `卒業証明書 ${getCertificateQuantity_(rowData, "その他_卒業証明書_枚数")}枚`,
      `使用目的、提出先など ${getCertificateValue_(rowData, "使用目的、提出先など")}`,
      "備考",
      getCertificateValue_(rowData, "備考"),
    );

    return lines.join("\n");
  }

  lines.push(
    `成績証明書 ${getCertificateQuantity_(rowData, "就職用_成績証明書_枚数")}枚`,
    `卒業見込書 ${getCertificateQuantity_(rowData, "就職用_卒業見込書_枚数")}枚`,
    `健康診断書 ${getCertificateQuantity_(rowData, "就職用_健康診断書_枚数")}枚`,
    `求人番号 ${getCertificateValue_(rowData, "求人番号")}`,
    `受験先企業名 ${getCertificateValue_(rowData, "受験先企業名")}`,
    `担任名 ${getCertificateValue_(rowData, "担任名")}`,
    `書類提出日 ${formatCertificateMonthDay_(getCertificateValue_(rowData, "書類提出日"))}`,
    "備考",
    getCertificateValue_(rowData, "備考"),
  );

  return lines.join("\n");
}

function getCertificatePhoneNumber_(rowData) {
  return getCertificateValue_(rowData, "電話番号（数字のみ）") || getCertificateValue_(rowData, "電話番号");
}

function getCertificateQuantity_(rowData, headerName) {
  const value = getCertificateValue_(rowData, headerName);
  const numberValue = Number(value || 0);

  return Number.isFinite(numberValue) ? String(Math.max(0, numberValue)) : "0";
}

function formatCertificateJapaneseEraDate_(value) {
  const date = parseCertificateDate_(value);
  if (!date) return value || "";

  const year = date.getFullYear();
  const monthDay = Utilities.formatDate(date, Session.getScriptTimeZone(), "MM月dd日");
  const eras = [
    { name: "令和", start: new Date(2019, 4, 1) },
    { name: "平成", start: new Date(1989, 0, 8) },
    { name: "昭和", start: new Date(1926, 11, 25) },
  ];

  for (const era of eras) {
    if (date >= era.start) {
      const eraYear = year - era.start.getFullYear() + 1;
      return `${era.name}${eraYear === 1 ? "元" : eraYear}年${monthDay}`;
    }
  }

  return Utilities.formatDate(date, Session.getScriptTimeZone(), "yyyy年MM月dd日");
}

function formatCertificateMonthDay_(value) {
  const date = parseCertificateDate_(value);
  if (!date) return value || "";

  return Utilities.formatDate(date, Session.getScriptTimeZone(), "MM月dd日");
}

function getCertificateReceiveDateText_() {
  const date = new Date();
  date.setDate(date.getDate() + 2);

  return Utilities.formatDate(date, Session.getScriptTimeZone(), "MM月dd日");
}

function parseCertificateDate_(value) {
  if (!value) return null;
  if (value instanceof Date && !Number.isNaN(value.getTime())) return value;

  const stringValue = value.toString().trim();
  const date = /^\d{4}-\d{2}-\d{2}$/.test(stringValue)
    ? new Date(`${stringValue}T00:00:00+09:00`)
    : new Date(stringValue);

  return Number.isNaN(date.getTime()) ? null : date;
}

function buildCertificateSchoolMailMap_(sheet) {
  if (!sheet) return {};

  const lastRow = sheet.getLastRow();
  if (lastRow < 1) return {};

  const values = sheet.getRange(1, 1, lastRow, 2).getValues();
  const map = {};

  values.forEach(([schoolName, mailAddress]) => {
    const normalizedSchoolName = schoolName ? schoolName.toString().trim() : "";
    const normalizedMailAddress = mailAddress ? mailAddress.toString().trim() : "";

    if (normalizedSchoolName === "学校名" || normalizedMailAddress === "メールアドレス") return;
    if (!normalizedSchoolName || !normalizedMailAddress) return;

    map[normalizedSchoolName] = normalizedMailAddress;
  });

  return map;
}

function buildCertificateTeacherMailMap_(sheet) {
  if (!sheet) return {};

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return {};

  const values = sheet.getRange(2, 1, lastRow - 1, 4).getValues();
  const map = {};

  values.forEach(([_schoolName, _courseName, teacherName, mailAddress]) => {
    const normalizedTeacherName = normalizeCertificateTeacherName_(teacherName);
    const normalizedMailAddress = mailAddress ? mailAddress.toString().trim() : "";

    if (!normalizedTeacherName || !normalizedMailAddress) return;

    if (!map[normalizedTeacherName]) {
      map[normalizedTeacherName] = [];
    }

    map[normalizedTeacherName].push(normalizedMailAddress);
  });

  Object.keys(map).forEach((teacherName) => {
    map[teacherName] = [...new Set(map[teacherName])].join(",");
  });

  return map;
}

function normalizeCertificateTeacherName_(teacherName) {
  if (!teacherName) return "";
  return teacherName.toString().trim().replace(/先生$/, "");
}

function getCertificateValue_(rowData, headerName) {
  if (rowData[headerName]) return rowData[headerName];

  const normalizedHeaderName = normalizeCertificateHeaderName_(headerName);
  return rowData[normalizedHeaderName] || "";
}

function normalizeCertificateHeaderName_(headerName) {
  return headerName
    .toString()
    .trim()
    .replace(/\[\]$/, "")
    .replace(/\s+/g, " ");
}

function createCertificateMailReportEntry_(recipientType, to, status, message) {
  return {
    recipientType,
    to,
    status,
    message,
    sentAt: new Date().toISOString(),
  };
}

function createCertificateJsonResponse_(payload) {
  return ContentService
    .createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}
