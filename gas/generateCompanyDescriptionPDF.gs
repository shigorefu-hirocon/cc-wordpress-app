const COMPANY_DESCRIPTION_SPREADSHEET_ID = "1AzvCp6NUZJ_xch5911sL7euOqy-Xi4wE4x18Bks0vqc";
const COMPANY_DESCRIPTION_FORM_RESPONSES_SHEET_NAME = "DB_訪問説明会";
const COMPANY_DESCRIPTION_TEMPLATE_SHEET_NAME = "訪問説明会報告書";
const COMPANY_DESCRIPTION_PDF_FOLDER_ID = "1gj1OgWrn1JsONHFkNLrpu-gf9oGL704F";

const COMPANY_DESCRIPTION_TEST_CAREER_CENTER_MAIL = "eduard@hsc.ac.jp";
const COMPANY_DESCRIPTION_PRODUCTION_CAREER_CENTER_MAIL = "syushoku@ucs-hiroshima.ac.jp";

function generateCompanyDescriptionPdfsFromFormResponses(e) {
  processCompanyDescriptionReport_(e, {
    sendEmails: true,
    deleteTempSheet: true,
  });
}

function testGenerateCompanyDescriptionPdfOnly() {
  processCompanyDescriptionReport_(null, {
    sendEmails: false,
    deleteTempSheet: true,
  });
}

function handleCompanyDescriptionWebhook_(e) {
  let tempSheet = null;

  try {
    const rowData = parseCompanyDescriptionRequestRowData_(e);
    const context = createCompanyDescriptionContext_();
    const normalizedRowData = normalizeCompanyDescriptionWebhookRowData_(rowData);

    const appendedRowNumber = appendCompanyDescriptionWebhookRowToSheet_(context.formResponsesSheet, normalizedRowData);

    if (!getCompanyDescriptionValue_(normalizedRowData, "訪問先")) {
      return createCompanyDescriptionJsonResponse_({ ok: false, message: "訪問先がありません。" });
    }

    tempSheet = createCompanyDescriptionTempTemplateSheet_(context.ss, context.templateSheet);

    fillCompanyDescriptionTemplate_(tempSheet, normalizedRowData);
    SpreadsheetApp.flush();

    const pdf = createCompanyDescriptionPdfFromSheet_(context.ss, tempSheet, context.folder, normalizedRowData);
    const mailReport = sendCompanyDescriptionReportEmails_(pdf.blob, normalizedRowData, context.schoolMailMap, context.teacherMailMap, context.careerCenterMail);

    writeCompanyDescriptionMailReportToSheet_(context.formResponsesSheet, appendedRowNumber, mailReport);
    context.ss.deleteSheet(tempSheet);
    tempSheet = null;

    return createCompanyDescriptionJsonResponse_({ ok: true, fileUrl: pdf.file.getUrl() });
  } catch (error) {
    console.error(error.stack || error.message || error);

    return createCompanyDescriptionJsonResponse_({
      ok: false,
      message: error.message || String(error),
      stack: error.stack || "",
    });
  } finally {
    if (tempSheet) {
      try {
        tempSheet.getParent().deleteSheet(tempSheet);
      } catch (cleanupError) {
        console.error(cleanupError.stack || cleanupError.message || cleanupError);
      }
    }
  }
}

function parseCompanyDescriptionRequestRowData_(e) {
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

function createCompanyDescriptionJsonResponse_(payload) {
  return ContentService
    .createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

function processCompanyDescriptionReport_(e, options) {
  const context = createCompanyDescriptionContext_();
  const rowData = getCompanyDescriptionSubmittedRowData_(context.formResponsesSheet, e);

  if (!getCompanyDescriptionValue_(rowData, "訪問先")) return;

  const tempSheet = createCompanyDescriptionTempTemplateSheet_(context.ss, context.templateSheet);

  fillCompanyDescriptionTemplate_(tempSheet, rowData);
  SpreadsheetApp.flush();

  const pdf = createCompanyDescriptionPdfFromSheet_(context.ss, tempSheet, context.folder, rowData);

  if (options.sendEmails) {
    const mailReport = sendCompanyDescriptionReportEmails_(pdf.blob, rowData, context.schoolMailMap, context.teacherMailMap, context.careerCenterMail);

    if (rowData.__submittedRowNumber) {
      writeCompanyDescriptionMailReportToSheet_(context.formResponsesSheet, rowData.__submittedRowNumber, mailReport);
    }
  }

  if (options.deleteTempSheet) {
    context.ss.deleteSheet(tempSheet);
  }

  return pdf.file;
}

function createCompanyDescriptionContext_() {
  const ss = SpreadsheetApp.openById(COMPANY_DESCRIPTION_SPREADSHEET_ID);
  const formResponsesSheet = getOrCreateSheet_(ss, COMPANY_DESCRIPTION_FORM_RESPONSES_SHEET_NAME);
  const templateSheet = ss.getSheetByName(COMPANY_DESCRIPTION_TEMPLATE_SHEET_NAME);
  const homeroomTeacherSheet = ss.getSheetByName("担任");
  const schoolMailAddressSheet = ss.getSheetByName("学校メールアドレス");
  const folder = DriveApp.getFolderById(COMPANY_DESCRIPTION_PDF_FOLDER_ID);

  if (!templateSheet) throw new Error(`シート「${COMPANY_DESCRIPTION_TEMPLATE_SHEET_NAME}」が見つかりません。`);

  const teacherMailMap = buildCompanyDescriptionTeacherMailMapFromTeacherSheet_(homeroomTeacherSheet);
  const schoolMailMap = buildCompanyDescriptionSchoolMailMapFromSchoolMailAddressSheet_(schoolMailAddressSheet);

  return {
    ss,
    formResponsesSheet,
    templateSheet,
    homeroomTeacherSheet,
    schoolMailAddressSheet,
    folder,
    schoolMailMap,
    teacherMailMap,
    careerCenterMail: getCompanyDescriptionCareerCenterMail_(),
  };
}

function getCompanyDescriptionCareerCenterMail_() {
  const useProductionMail = PropertiesService
    .getScriptProperties()
    .getProperty("USE_PRODUCTION_CAREER_CENTER_MAIL") === "true";

  return useProductionMail
    ? COMPANY_DESCRIPTION_PRODUCTION_CAREER_CENTER_MAIL
    : COMPANY_DESCRIPTION_TEST_CAREER_CENTER_MAIL;
}

function getOrCreateSheet_(ss, sheetName) {
  const sheet = ss.getSheetByName(sheetName);
  if (sheet) return sheet;

  return ss.insertSheet(sheetName);
}

function fillCompanyDescriptionTemplate_(sheet, rowData) {
  sheet.getRange("C5").setValue("");
  sheet.getRange("F5").setValue("");
  sheet.getRange("C7").setValue("");
  sheet.getRange("E7").setValue("");
  sheet.getRange("C13").setValue("");
  sheet.getRange("F13").setValue("");
  sheet.getRange("P13").setValue("");

  markCompanyDescriptionMappedCell_(sheet, {
    企業訪問: "C5",
    企業説明会: "F5",
  }, getCompanyDescriptionValue_(rowData, "項目"));

  sheet.getRange("C6").setValue(getCompanyDescriptionValue_(rowData, "訪問先"));
  sheet.getRange("L6").setValue(getCompanyDescriptionValue_(rowData, "本社"));

  markCompanyDescriptionMappedCell_(sheet, {
    本社: "C7",
    その他: "E7",
  }, getCompanyDescriptionValue_(rowData, "会場"));
  sheet.getRange("I7").setValue(getCompanyDescriptionValue_(rowData, "その他会場"));

  sheet.getRange("C8").setValue(formatCompanyDescriptionDateValue_(getCompanyDescriptionValue_(rowData, "日時")));
  sheet.getRange("L8").setValue(formatCompanyDescriptionTimeValue_(getCompanyDescriptionValue_(rowData, "時間（開始）")));
  sheet.getRange("P8").setValue(formatCompanyDescriptionTimeValue_(getCompanyDescriptionValue_(rowData, "時間（終了）")));

  sheet.getRange("D9").setValue(getCompanyDescriptionValue_(rowData, "面談者 部署・役職1"));
  sheet.getRange("K9").setValue(getCompanyDescriptionValue_(rowData, "面談者 部署・役職2"));

  sheet.getRange("C11").setValue(getCompanyDescriptionValue_(rowData, "学校名"));
  sheet.getRange("J11").setValue(getCompanyDescriptionValue_(rowData, "コース名"));
  sheet.getRange("Q11").setValue(getCompanyDescriptionValue_(rowData, "性別"));
  sheet.getRange("N1").setValue(formatCompanyDescriptionJapaneseEraDate_(getCompanyDescriptionValue_(rowData, "提出日") || new Date()));

  markCompanyDescriptionMappedCell_(sheet, {
    希望する: "C13",
    希望しない: "F13",
    検討中: "P13",
  }, getCompanyDescriptionValue_(rowData, "受験意思"));
  sheet.getRange("J13").setValue(getCompanyDescriptionValue_(rowData, "希望しない理由"));

  sheet.getRange("A17").setValue(getCompanyDescriptionValue_(rowData, "感想"));
}

function getCompanyDescriptionSubmittedRowData_(sheet, e) {
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  const submittedRowNumber = e && e.range ? e.range.getRow() : sheet.getLastRow();
  const values = e && e.values
    ? e.values
    : sheet.getRange(submittedRowNumber, 1, 1, sheet.getLastColumn()).getValues()[0];

  const rowData = buildCompanyDescriptionRowObject_(headers, values);
  rowData.__submittedRowNumber = submittedRowNumber;

  return rowData;
}

function buildCompanyDescriptionRowObject_(headers, values) {
  const rowData = {};

  headers.forEach((header, index) => {
    if (!header) return;
    const cleanHeader = header.toString().trim();
    rowData[cleanHeader] = values[index];
    rowData[normalizeCompanyDescriptionHeaderName_(cleanHeader)] = values[index];
  });

  return rowData;
}

function createCompanyDescriptionTempTemplateSheet_(ss, templateSheet) {
  const tempSheet = templateSheet.copyTo(ss);
  tempSheet.setName(`一時_訪問説明会_${Date.now()}`);
  ss.setActiveSheet(tempSheet);
  return tempSheet;
}

function createCompanyDescriptionPdfFromSheet_(ss, sheet, folder, rowData) {
  const pdfName = buildCompanyDescriptionPdfName_(rowData);
  const url = buildCompanyDescriptionPdfExportUrl_(ss, sheet);
  const token = ScriptApp.getOAuthToken();
  const response = UrlFetchApp.fetch(url, {
    headers: { Authorization: "Bearer " + token },
  });

  trashCompanyDescriptionExistingFilesByName_(folder, pdfName);

  const file = folder.createFile(response.getBlob().setName(pdfName));
  const blob = file.getBlob();

  return {
    file,
    blob,
    name: pdfName,
  };
}

function buildCompanyDescriptionPdfName_(rowData) {
  return `${sanitizeCompanyDescriptionFileName_(getCompanyDescriptionValue_(rowData, "訪問先"))}-${getCompanyDescriptionVisitDateText_(rowData)}-${sanitizeCompanyDescriptionFileName_(getCompanyDescriptionValue_(rowData, "学籍番号"))}.pdf`;
}

function buildCompanyDescriptionPdfExportUrl_(ss, sheet) {
  return `https://docs.google.com/spreadsheets/d/${ss.getId()}/export?format=pdf&gid=${sheet.getSheetId()}` +
    "&size=A4" +
    "&portrait=true" +
    "&fitw=true" +
    "&gridlines=false" +
    "&sheetnames=false" +
    "&pagenumbers=false" +
    "&top_margin=0.25" +
    "&bottom_margin=0.25" +
    "&left_margin=0.25" +
    "&right_margin=0.25";
}

function trashCompanyDescriptionExistingFilesByName_(folder, fileName) {
  const files = folder.getFilesByName(fileName);
  while (files.hasNext()) {
    files.next().setTrashed(true);
  }
}

function sendCompanyDescriptionReportEmails_(pdfBlob, rowData, schoolMailMap, teacherMailMap, careerCenterMail) {
  return [
    sendCompanyDescriptionSchoolReportEmail_(pdfBlob, rowData, schoolMailMap),
    sendCompanyDescriptionTeacherReportEmail_(pdfBlob, rowData, teacherMailMap),
    sendCompanyDescriptionCareerCenterReportEmail_(pdfBlob, rowData, careerCenterMail),
    sendCompanyDescriptionStudentReportEmail_(pdfBlob, rowData),
  ];
}

function sendCompanyDescriptionSchoolReportEmail_(pdfBlob, rowData, schoolMailMap) {
  const schoolName = getCompanyDescriptionValue_(rowData, "学校名");
  const schoolMailTo = schoolMailMap[schoolName];

  if (!schoolMailTo) {
    return createCompanyDescriptionMailReportEntry_("学校", "", "skipped", "学校メールアドレスが未登録です。");
  }

  return sendCompanyDescriptionStaffReportEmail_(pdfBlob, rowData, schoolMailTo, "学校");
}

function sendCompanyDescriptionTeacherReportEmail_(pdfBlob, rowData, teacherMailMap) {
  const teacherName = getCompanyDescriptionValue_(rowData, "担任");
  const normalizedTeacherName = normalizeCompanyDescriptionTeacherName_(teacherName);
  const teacherMailTo = teacherMailMap[normalizedTeacherName];

  if (!teacherMailTo) {
    return createCompanyDescriptionMailReportEntry_("担任", "", "skipped", "担任メールアドレスが未登録です。");
  }

  return sendCompanyDescriptionStaffReportEmail_(pdfBlob, rowData, teacherMailTo, "担任");
}

function sendCompanyDescriptionStaffReportEmail_(pdfBlob, rowData, to, recipientType) {
  const subject = `訪問説明会-${getCompanyDescriptionValue_(rowData, "訪問先")}-${getCompanyDescriptionValue_(rowData, "名前")}-${getCompanyDescriptionVisitDateText_(rowData)}`;
  const body = `コース：${getCompanyDescriptionValue_(rowData, "コース名")}\n学籍番号：${getCompanyDescriptionValue_(rowData, "学籍番号")}\n名前：${getCompanyDescriptionValue_(rowData, "名前")}`;

  return sendCompanyDescriptionEmailWithReport_(to, subject, body, pdfBlob, recipientType);
}

function sendCompanyDescriptionCareerCenterReportEmail_(pdfBlob, rowData, careerCenterMail) {
  if (!careerCenterMail) {
    return createCompanyDescriptionMailReportEntry_("就職センター", "", "skipped", "就職センターメールアドレスが未設定です。");
  }

  const subject = `訪問説明会-${getCompanyDescriptionValue_(rowData, "訪問先")}-${getCompanyDescriptionVisitDateText_(rowData)}-${getCompanyDescriptionValue_(rowData, "学籍番号")}`;
  const body = `コース：${getCompanyDescriptionValue_(rowData, "コース名")}\n名前：${getCompanyDescriptionValue_(rowData, "名前")}\n学籍番号：${getCompanyDescriptionValue_(rowData, "学籍番号")}`;

  return sendCompanyDescriptionEmailWithReport_(careerCenterMail, subject, body, pdfBlob, "就職センター");
}

function sendCompanyDescriptionStudentReportEmail_(pdfBlob, rowData) {
  const studentMailTo = getCompanyDescriptionValue_(rowData, "メールアドレス").toString().trim();
  const studentName = getCompanyDescriptionValue_(rowData, "名前");

  if (!studentMailTo) {
    return createCompanyDescriptionMailReportEntry_("学生", "", "skipped", "学生メールアドレスが未入力です。");
  }

  const subject = "【企業訪問・企業説明会報告書確認】PDFを送付します";
  const body = `${studentName} さん\n\n企業訪問・企業説明会報告書のPDFを送付されました。\n内容を確認してください。\n\n※このメールは自動送信です。`;

  return sendCompanyDescriptionEmailWithReport_(studentMailTo, subject, body, pdfBlob, "学生");
}

function sendCompanyDescriptionEmailWithReport_(to, subject, body, pdfBlob, recipientType) {
  try {
    GmailApp.sendEmail(
      to,
      subject,
      body,
      { attachments: [pdfBlob], name: "就職試験管理システム" },
    );

    return createCompanyDescriptionMailReportEntry_(recipientType, to, "sent", "");
  } catch (error) {
    return createCompanyDescriptionMailReportEntry_(recipientType, to, "error", error.message);
  }
}

function createCompanyDescriptionMailReportEntry_(recipientType, to, status, message) {
  return {
    recipientType,
    to,
    status,
    message,
    sentAt: new Date().toISOString(),
  };
}

function buildCompanyDescriptionSchoolMailMapFromSchoolMailAddressSheet_(sheet) {
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

function buildCompanyDescriptionTeacherMailMapFromTeacherSheet_(sheet) {
  if (!sheet) return {};

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return {};

  const values = sheet.getRange(2, 1, lastRow - 1, 4).getValues();
  const map = {};

  values.forEach(([_schoolName, _courseName, teacherName, mailAddress]) => {
    const normalizedTeacherName = normalizeCompanyDescriptionTeacherName_(teacherName);
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

function appendCompanyDescriptionWebhookRowToSheet_(sheet, rowData) {
  const submittedHeaders = Object.keys(rowData).filter((header) => !header.startsWith("__"));
  const headers = ensureCompanyDescriptionFormResponseHeaders_(sheet, [...submittedHeaders, "メール送信レポート"]);

  const values = headers.map((header) => {
    if (!header) return "";
    return getCompanyDescriptionValue_(rowData, header.toString().trim());
  });

  sheet.appendRow(values);
  return sheet.getLastRow();
}

function writeCompanyDescriptionMailReportToSheet_(sheet, rowNumber, mailReport) {
  if (!rowNumber) return;

  const headers = ensureCompanyDescriptionFormResponseHeaders_(sheet, ["メール送信レポート"]);
  const reportColumnIndex = headers.indexOf("メール送信レポート") + 1;

  if (reportColumnIndex < 1) return;

  sheet
    .getRange(rowNumber, reportColumnIndex)
    .setValue(JSON.stringify(mailReport, null, 2));
}

function ensureCompanyDescriptionFormResponseHeaders_(sheet, requiredHeaders) {
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

function normalizeCompanyDescriptionWebhookRowData_(rowData) {
  const normalized = {};

  Object.keys(rowData).forEach((key) => {
    const value = rowData[key];
    const normalizedKey = normalizeCompanyDescriptionHeaderName_(key);
    normalized[normalizedKey] = Array.isArray(value) ? value.filter(Boolean).join("\n") : value;
  });

  return normalized;
}

function clearCompanyDescriptionMappedCells_(sheet, cellMap) {
  [...new Set(Object.values(cellMap))].forEach((cell) => {
    sheet.getRange(cell).setValue("");
  });
}

function markCompanyDescriptionMappedCell_(sheet, cellMap, value) {
  if (!value) return;

  const normalizedValue = value.toString().trim();
  if (!cellMap[normalizedValue]) return;

  sheet.getRange(cellMap[normalizedValue]).setValue("〇");
}

function getCompanyDescriptionValue_(rowData, headerName) {
  if (rowData[headerName]) return rowData[headerName];

  const normalizedHeaderName = normalizeCompanyDescriptionHeaderName_(headerName);
  return rowData[normalizedHeaderName] || "";
}

function normalizeCompanyDescriptionHeaderName_(headerName) {
  return headerName
    .toString()
    .trim()
    .replace(/^[\u2460-\u246B]/, "")
    .replace(/\u2460/g, "1")
    .replace(/\u2461/g, "2")
    .replace(/\u2462/g, "3")
    .replace(/\u2463/g, "4")
    .replace(/\u2464/g, "5")
    .replace(/\u2465/g, "6")
    .replace(/\u2466/g, "7")
    .replace(/\u2467/g, "8")
    .replace(/\u2468/g, "9")
    .replace(/\u2469/g, "10")
    .replace(/\u246A/g, "11")
    .replace(/\u246B/g, "12")
    .replace(/\s+/g, " ");
}

function getCompanyDescriptionVisitDateText_(rowData) {
  const visitDate = getCompanyDescriptionValue_(rowData, "日時");
  const formattedDate = formatCompanyDescriptionDateValue_(visitDate);

  if (!formattedDate) {
    return Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyyMMdd");
  }

  return formattedDate.toString().replace(/\//g, "");
}

function sanitizeCompanyDescriptionFileName_(value) {
  return value
    ? value.toString().trim().replace(/[\\/:*?"<>|]/g, "_")
    : "未入力";
}

function formatCompanyDescriptionDateValue_(value) {
  if (!value) return "";

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return Utilities.formatDate(
    date,
    Session.getScriptTimeZone(),
    "yyyy/MM/dd",
  );
}

function formatCompanyDescriptionTimeValue_(value) {
  if (!value) return "";

  if (typeof value === "string" && /^\d{2}:\d{2}/.test(value)) {
    return value.slice(0, 5);
  }

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return Utilities.formatDate(
    date,
    Session.getScriptTimeZone(),
    "HH:mm",
  );
}

function formatCompanyDescriptionJapaneseEraDate_(value) {
  if (!value) return "";

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  const year = Number(Utilities.formatDate(date, Session.getScriptTimeZone(), "yyyy"));
  const month = Utilities.formatDate(date, Session.getScriptTimeZone(), "M");
  const day = Utilities.formatDate(date, Session.getScriptTimeZone(), "d");
  const reiwaYear = year - 2018;
  const reiwaText = reiwaYear === 1 ? "元" : reiwaYear.toString();

  return `令和${reiwaText}年${month}月${day}日`;
}

function normalizeCompanyDescriptionTeacherName_(teacherName) {
  if (!teacherName) return "";
  return teacherName.toString().trim().replace(/先生$/, "");
}
