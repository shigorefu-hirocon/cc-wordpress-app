const SPREADSHEET_ID = "1AzvCp6NUZJ_xch5911sL7euOqy-Xi4wE4x18Bks0vqc";
const TEST_CAREER_CENTER_MAIL = "eduard@hsc.ac.jp";
const PRODUCTION_CAREER_CENTER_MAIL = "syushoku@ucs-hiroshima.ac.jp";

function generatePdfsFromFormResponses(e) {
  processExamReport_(e, {
    sendEmails: true,
    deleteTempSheet: true,
  });
}

function testGeneratePdfOnly() {
  processExamReport_(null, {
    sendEmails: false,
    deleteTempSheet: true,
  });
}

function doGet(e) {
  return routeWebhook_(e);
}

function doPost(e) {
  return routeWebhook_(e);
}

function routeWebhook_(e) {
  let rowData = {};

  try {
    rowData = parseRouteRowData_(e);
  } catch (error) {
    return createRouteJsonResponse_({
      ok: false,
      message: error.message || String(error),
    });
  }

  if (!Object.keys(rowData).length) {
    return createRouteJsonResponse_({
      ok: false,
      message: "payload がありません。",
    });
  }

  if (isCompanyDescriptionPayload_(rowData)) {
    return handleCompanyDescriptionWebhook_(e);
  }

  if (isCertificatePayload_(rowData)) {
    return handleCertificateWebhook_(e);
  }

  return handleWebhook_(e);
}

function parseRouteRowData_(e) {
  if (!e) return {};

  if (e.parameter && e.parameter.payload) {
    return JSON.parse(e.parameter.payload);
  }

  if (e.postData && e.postData.contents) {
    return JSON.parse(e.postData.contents);
  }

  return {};
}

function isCompanyDescriptionPayload_(rowData) {
  return rowData["レポート種別"] === "企業訪問・企業説明会報告書" || Boolean(rowData["訪問先"]);
}

function isCertificatePayload_(rowData) {
  return rowData["申請種別"] === "証明書発行申請" || Boolean(rowData["選択した証明書"]);
}

function createRouteJsonResponse_(payload) {
  return ContentService
    .createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

function handleWebhook_(e) {
  const rowData = e.parameter && e.parameter.payload
    ? JSON.parse(e.parameter.payload)
    : JSON.parse(e.postData.contents);

  const context = createContext_();
  const normalizedRowData = normalizeWebhookRowData_(rowData);

  const appendedRowNumber = appendWebhookRowToSheet_(context.formResponsesSheet, normalizedRowData);

  if (!getValue_(normalizedRowData, "受験先企業名")) {
    return ContentService
      .createTextOutput(JSON.stringify({ ok: false, message: "受験先企業名がありません。" }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  const tempSheet = createTempTemplateSheet_(context.ss, context.templateSheet);

  fillTemplate_(tempSheet, normalizedRowData);
  SpreadsheetApp.flush();

  const pdf = createPdfFromSheet_(context.ss, tempSheet, context.folder, normalizedRowData);

  const mailReport = sendReportEmails_(pdf.blob, normalizedRowData, context.schoolMailMap, context.teacherMailMap, context.careerCenterMail);
  writeMailReportToSheet_(context.formResponsesSheet, appendedRowNumber, mailReport);
  context.ss.deleteSheet(tempSheet);

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true, fileUrl: pdf.file.getUrl() }))
    .setMimeType(ContentService.MimeType.JSON);
}

function processExamReport_(e, options) {
  const context = createContext_();
  const rowData = getSubmittedRowData_(context.formResponsesSheet, e);

  if (!getValue_(rowData, "受験先企業名")) return;

  const tempSheet = createTempTemplateSheet_(context.ss, context.templateSheet);

  fillTemplate_(tempSheet, rowData);
  SpreadsheetApp.flush();

  const pdf = createPdfFromSheet_(context.ss, tempSheet, context.folder, rowData);

  if (options.sendEmails) {
    const mailReport = sendReportEmails_(pdf.blob, rowData, context.schoolMailMap, context.teacherMailMap, context.careerCenterMail);

    if (rowData.__submittedRowNumber) {
      writeMailReportToSheet_(context.formResponsesSheet, rowData.__submittedRowNumber, mailReport);
    }
  }

  if (options.deleteTempSheet) {
    context.ss.deleteSheet(tempSheet);
  }

  return pdf.file;
}

function createContext_() {
  const ss = SpreadsheetApp.openById(SPREADSHEET_ID);
  const formResponsesSheet = ss.getSheetByName("フォームの回答");
  const templateSheet = ss.getSheetByName("テンプレート");
  const homeroomTeacherSheet = ss.getSheetByName("担任");
  const schoolMailAddressSheet = ss.getSheetByName("学校メールアドレス");
  const folder = DriveApp.getFolderById("1gj1OgWrn1JsONHFkNLrpu-gf9oGL704F");
  const careerCenterMail = getCareerCenterMail_();

  if (!formResponsesSheet) throw new Error('シート「フォームの回答」が見つかりません。');
  if (!templateSheet) throw new Error('シート「テンプレート」が見つかりません。');

  const teacherMailMap = buildTeacherMailMapFromTeacherSheet_(homeroomTeacherSheet);
  const schoolMailMap = buildSchoolMailMapFromSchoolMailAddressSheet_(schoolMailAddressSheet);

  return {
    ss,
    formResponsesSheet,
    templateSheet,
    homeroomTeacherSheet,
    schoolMailAddressSheet,
    folder,
    schoolMailMap,
    teacherMailMap,
    careerCenterMail,
  };
}

function getCareerCenterMail_() {
  const useProductionMail = PropertiesService
    .getScriptProperties()
    .getProperty("USE_PRODUCTION_CAREER_CENTER_MAIL") === "true";

  return useProductionMail
    ? PRODUCTION_CAREER_CENTER_MAIL
    : TEST_CAREER_CENTER_MAIL;
}

function getSubmittedRowData_(sheet, e) {
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  const submittedRowNumber = e && e.range ? e.range.getRow() : sheet.getLastRow();
  const values = e && e.values
    ? e.values
    : sheet.getRange(submittedRowNumber, 1, 1, sheet.getLastColumn()).getValues()[0];

  const rowData = buildRowObject_(headers, values);
  rowData.__submittedRowNumber = submittedRowNumber;

  return rowData;
}

function buildRowObject_(headers, values) {
  const rowData = {};

  headers.forEach((header, index) => {
    if (!header) return;
    const cleanHeader = header.toString().trim();
    rowData[cleanHeader] = values[index];
    rowData[normalizeHeaderName_(cleanHeader)] = values[index];
  });

  return rowData;
}

function createTempTemplateSheet_(ss, templateSheet) {
  const tempSheet = templateSheet.copyTo(ss);
  tempSheet.setName(`一時テンプレート_${Date.now()}`);
  ss.setActiveSheet(tempSheet);
  return tempSheet;
}

function fillTemplate_(sheet, rowData) {
  fillBasicInfo_(sheet, rowData);
  fillApplyMethod_(sheet, rowData);
  fillInterview_(sheet, rowData);
  fillGroupDiscussion_(sheet, rowData);
  fillWrittenExam_(sheet, rowData);
  fillCompositionExam_(sheet, rowData);
  fillAptitudeTest_(sheet, rowData);
  fillSubmitterInfo_(sheet, rowData);
}

function fillBasicInfo_(sheet, rowData) {
  sheet.getRange("P1").setValue(new Date());
  sheet.getRange("C6").setValue(getValue_(rowData, "受験先企業名"));
  sheet.getRange("O6").setValue(getValue_(rowData, "本社"));

  const examStartDate = getValue_(rowData, "受験日（開始）") || getValue_(rowData, "受験日時（開始）");
  const examStartTime = getValue_(rowData, "受験時間（開始）") || getValue_(rowData, "受験日時（開始）");
  const examEndTime = getValue_(rowData, "受験時間（終了）") || getValue_(rowData, "受験日時（終了）");

  sheet.getRange("C7").setValue(formatDateValue_(examStartDate));
  sheet.getRange("H7").setValue(formatTimeValue_(examStartTime));
  sheet.getRange("K7").setValue(formatTimeValue_(examEndTime));

  sheet.getRange("O7").setValue(getValue_(rowData, "受験場所"));
  sheet.getRange("C8").setValue(getValue_(rowData, "業種名"));
  sheet.getRange("L8").setValue(getValue_(rowData, "職種名"));
}

function fillApplyMethod_(sheet, rowData) {
  const applyMethodCellMap = {
    学校求人: "C9",
    縁故: "C10",
    自己開拓: "F9",
    ハローワーク: "F10",
    "就職サイト／会社ＨＰ": "I9",
    "就職サイト/会社HP": "I9",
    "その他（エージェント等）": "I10",
  };

  clearMappedCells_(sheet, applyMethodCellMap);
  markMappedCell_(sheet, applyMethodCellMap, getValue_(rowData, "応募方法"));

  sheet
    .getRange("O9")
    .setValue(getValue_(rowData, "利用した就職サイト／会社ＨＰを記入してください。"));
  sheet
    .getRange("O10")
    .setValue(getValue_(rowData, "その他利用したエージェント等を記入してください。"));
}

function fillInterview_(sheet, rowData) {
  sheet.getRange("K12").setValue(getValue_(rowData, "今回の面接試験は何次試験ですか？"));
  sheet.getRange("L13").setValue(getValue_(rowData, "面接時間（約何分）"));
  sheet.getRange("Q13").setValue(getValue_(rowData, "面接官の人数"));

  const interviewMethodCellMap = {
    オンライン面接: "D14",
    "対面（個人面接）": "H14",
    "対面（個人）面接": "H14",
    "対面（集団面接）": "L14",
    "対面（集団）面接": "L14",
  };

  clearMappedCells_(sheet, interviewMethodCellMap);
  markMappedCell_(sheet, interviewMethodCellMap, getValue_(rowData, "面接方法について"));

  sheet.getRange("R14").setValue(getValue_(rowData, "受験人数（およそで構いません）"));
  sheet
    .getRange("G16")
    .setValue(
      getFirstValue_(rowData, [
        "②質問に対してどのように返答したか？＜志望動機について＞※簡潔にまとめて記述",
        "質問に対してどのように返答したか？＜志望動機について＞※簡潔にまとめて記述",
        "志望動機",
      ]),
    );
  sheet
    .getRange("G17")
    .setValue(
      getFirstValue_(rowData, [
        "②質問に対してどのように返答したか？＜自己ＰＲについて＞※簡潔にまとめて記述",
        "質問に対してどのように返答したか？＜自己ＰＲについて＞※簡潔にまとめて記述",
        "自己ＰＲ",
        "自己PR",
      ]),
    );
  sheet
    .getRange("B18")
    .setValue(getValue_(rowData, "質問1"));
  sheet
    .getRange("G18")
    .setValue(getValue_(rowData, "答え1"));
  sheet
    .getRange("B19")
    .setValue(getValue_(rowData, "質問2"));
  sheet
    .getRange("G19")
    .setValue(getValue_(rowData, "答え2"));
  sheet
    .getRange("B20")
    .setValue(getValue_(rowData, "質問3"));
  sheet
    .getRange("G20")
    .setValue(getValue_(rowData, "答え3"));
  sheet.getRange("A22").setValue(getValue_(rowData, "面接試験内容補足（書き方自由）"));
}

function fillGroupDiscussion_(sheet, rowData) {
  sheet.getRange("H25").setValue(getValue_(rowData, "グループディスカッション実施時間（約何分）"));
  sheet.getRange("K25").setValue(getValue_(rowData, "ディスカッション試験管人数"));
  sheet.getRange("O25").setValue(getValue_(rowData, "グループ人数"));
  sheet.getRange("S25").setValue(getValue_(rowData, "グループ数"));
  sheet.getRange("C26").setValue(getValue_(rowData, "テーマ"));
  sheet.getRange("C27").setValue(getValue_(rowData, "実施感想および気づき"));
}

function fillWrittenExam_(sheet, rowData) {
  sheet.getRange("R29").setValue(getValue_(rowData, "筆記試験時間（単位：約何分）"));
  sheet.getRange("A30").setValue(getValue_(rowData, "筆記試験　問題など具体的に記述してください。"));
}

function fillCompositionExam_(sheet, rowData) {
  sheet.getRange("G35").setValue(getValue_(rowData, "作文試験時間（単位：約何分）"));
  sheet.getRange("M35").setValue(getValue_(rowData, "原稿用紙・レポート用紙　枚数"));
  sheet.getRange("Q35").setValue(getValue_(rowData, "作文試験　文字数"));
  sheet.getRange("C36").setValue(getValue_(rowData, "作文試験　テーマ"));
}

function fillAptitudeTest_(sheet, rowData) {
  sheet.getRange("G38").setValue(getValue_(rowData, "適性検査時間（単位：分）"));

  const aptitudeMethodCellMap = {
    "Ｗｅｂ（オンライン）": "M38",
    "Ｗｅｂテスティング": "M38",
    "Webテスティング": "M38",
    ペーパーベース: "Q38",
    ペーパテスティング: "Q38",
    ペーパーテスティング: "Q38",
  };

  clearMappedCells_(sheet, aptitudeMethodCellMap);
  markMappedCell_(sheet, aptitudeMethodCellMap, getValue_(rowData, "試験実施方法"));

  const aptitudeTypeCellMap = {
    ＳＰＩ: "A39",
    SPI: "A39",
    "Ｖ－ＣＡＴ": "C39",
    "V-CAT": "C39",
    クレペリン: "E39",
    Ｃｕｂｉｃ: "H39",
    Cubic: "H39",
    "ＧＡＢ・ＣＡＢ": "K39",
    "GAB・CAB": "K39",
    その他: "N39",
  };

  clearMappedCells_(sheet, aptitudeTypeCellMap);
  markMappedCell_(sheet, aptitudeTypeCellMap, getValue_(rowData, "適性検査種類"));
}

function fillSubmitterInfo_(sheet, rowData) {
  sheet.getRange("C42").setValue(getValue_(rowData, "学校名"));
  sheet.getRange("K42").setValue(getValue_(rowData, "コース名"));
  sheet.getRange("S42").setValue(getValue_(rowData, "性別"));
  sheet
    .getRange("A45")
    .setValue(getValue_(rowData, "就職試験の感想および後輩へのアドバイス"));
}

function createPdfFromSheet_(ss, sheet, folder, rowData) {
  const pdfName = buildPdfName_(rowData);
  const url = buildPdfExportUrl_(ss, sheet);
  const token = ScriptApp.getOAuthToken();
  const response = UrlFetchApp.fetch(url, {
    headers: { Authorization: "Bearer " + token },
  });

  trashExistingFilesByName_(folder, pdfName);

  const file = folder.createFile(response.getBlob().setName(pdfName));
  const blob = file.getBlob();

  return {
    file,
    blob,
    name: pdfName,
  };
}

function buildPdfName_(rowData) {
  return `受験-${sanitizeFileName_(getValue_(rowData, "受験先企業名"))}-${getExamDateText_(rowData)}-${sanitizeFileName_(getValue_(rowData, "学籍番号"))}.pdf`;
}

function buildPdfExportUrl_(ss, sheet) {
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

function trashExistingFilesByName_(folder, fileName) {
  const files = folder.getFilesByName(fileName);
  while (files.hasNext()) {
    files.next().setTrashed(true);
  }
}

function sendReportEmails_(pdfBlob, rowData, schoolMailMap, teacherMailMap, careerCenterMail) {
  return [
    sendSchoolReportEmail_(pdfBlob, rowData, schoolMailMap),
    sendTeacherReportEmail_(pdfBlob, rowData, teacherMailMap),
    sendCareerCenterReportEmail_(pdfBlob, rowData, careerCenterMail),
    sendStudentReportEmail_(pdfBlob, rowData),
  ];
}

function sendSchoolReportEmail_(pdfBlob, rowData, schoolMailMap) {
  const schoolName = getValue_(rowData, "学校名");
  const schoolMailTo = schoolMailMap[schoolName];

  if (!schoolMailTo) {
    return createMailReportEntry_("学校", "", "skipped", "学校メールアドレスが未登録です。");
  }

  return sendStaffReportEmail_(pdfBlob, rowData, schoolMailTo, "学校");
}

function sendTeacherReportEmail_(pdfBlob, rowData, teacherMailMap) {
  const teacherName = getValue_(rowData, "担任");
  const normalizedTeacherName = normalizeTeacherName_(teacherName);
  const teacherMailTo = teacherMailMap[normalizedTeacherName];

  if (!teacherMailTo) {
    return createMailReportEntry_("担任", "", "skipped", "担任メールアドレスが未登録です。");
  }

  return sendStaffReportEmail_(pdfBlob, rowData, teacherMailTo, "担任");
}

function sendStaffReportEmail_(pdfBlob, rowData, to, recipientType) {
  const subject = `受験-${getValue_(rowData, "受験先企業名")}-${getValue_(rowData, "名前")}-${getExamDateText_(rowData)}`;
  const body = `コース：${getValue_(rowData, "コース名")}\n学籍番号：${getValue_(rowData, "学籍番号")}\n名前：${getValue_(rowData, "名前")}`;

  return sendEmailWithReport_(to, subject, body, pdfBlob, recipientType);
}

function sendCareerCenterReportEmail_(pdfBlob, rowData, careerCenterMail) {
  if (!careerCenterMail) {
    return createMailReportEntry_("就職センター", "", "skipped", "就職センターメールアドレスが未設定です。");
  }

  const subject = `受験-${getValue_(rowData, "受験先企業名")}-${getExamDateText_(rowData)}-${getValue_(rowData, "学籍番号")}`;
  const body = `コース：${getValue_(rowData, "コース名")}\n名前：${getValue_(rowData, "名前")}\n学籍番号：${getValue_(rowData, "学籍番号")}`;

  return sendEmailWithReport_(careerCenterMail, subject, body, pdfBlob, "就職センター");
}

function sendStudentReportEmail_(pdfBlob, rowData) {
  const studentMailTo = getValue_(rowData, "メールアドレス").toString().trim();
  const studentName = getValue_(rowData, "名前");

  if (!studentMailTo) {
    return createMailReportEntry_("学生", "", "skipped", "学生メールアドレスが未入力です。");
  }

  const subject = "受験-【就職試験報告書確認】PDFを送付します";
  const body = `${studentName} さん\n\n就職試験報告書のPDFを送付されました。\n内容を確認してください。\n\n※このメールは自動送信です。`;

  return sendEmailWithReport_(studentMailTo, subject, body, pdfBlob, "学生");
}

function sendEmailWithReport_(to, subject, body, pdfBlob, recipientType) {
  try {
    GmailApp.sendEmail(
      to,
      subject,
      body,
      { attachments: [pdfBlob], name: "就職試験管理システム" },
    );

    return createMailReportEntry_(recipientType, to, "sent", "");
  } catch (error) {
    return createMailReportEntry_(recipientType, to, "error", error.message);
  }
}

function createMailReportEntry_(recipientType, to, status, message) {
  return {
    recipientType,
    to,
    status,
    message,
    sentAt: new Date().toISOString(),
  };
}

function buildSchoolMailMapFromSchoolMailAddressSheet_(sheet) {
  if (!sheet) return {};

  const lastRow = sheet.getLastRow();
  if (lastRow < 1) return {};

  // A列: 学校名, B列: メールアドレス
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

function buildTeacherMailMapFromTeacherSheet_(sheet) {
  if (!sheet) {
    throw new Error('シート「担任」が見つかりません。');
  }

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return {};

  // A列: 学校, B列: コース, C列: 担任, D列: メールアドレス
  const values = sheet.getRange(2, 1, lastRow - 1, 4).getValues();
  const map = {};

  values.forEach(([_schoolName, _courseName, teacherName, mailAddress]) => {
    const normalizedTeacherName = normalizeTeacherName_(teacherName);
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

function appendWebhookRowToSheet_(sheet, rowData) {
  const headers = ensureFormResponseHeaders_(sheet, ["メールアドレス", "メール送信レポート"]);

  const values = headers.map((header) => {
    if (!header) return "";
    return getValue_(rowData, header.toString().trim());
  });

  sheet.appendRow(values);
  return sheet.getLastRow();
}

function writeMailReportToSheet_(sheet, rowNumber, mailReport) {
  if (!rowNumber) return;

  const headers = ensureFormResponseHeaders_(sheet, ["メール送信レポート"]);
  const reportColumnIndex = headers.indexOf("メール送信レポート") + 1;

  if (reportColumnIndex < 1) return;

  sheet
    .getRange(rowNumber, reportColumnIndex)
    .setValue(JSON.stringify(mailReport, null, 2));
}

function ensureFormResponseHeaders_(sheet, requiredHeaders) {
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

function normalizeWebhookRowData_(rowData) {
  const normalized = {};

  Object.keys(rowData).forEach((key) => {
    const value = rowData[key];
    const normalizedKey = normalizeHeaderName_(key);
    normalized[normalizedKey] = Array.isArray(value) ? value.filter(Boolean).join("\n") : value;
  });

  normalized["受験日時（開始）"] = joinDateTime_(
    getValue_(normalized, "受験日（開始）"),
    getValue_(normalized, "受験時間（開始）"),
  );
  normalized["受験日時（終了）"] = joinDateTime_(
    getValue_(normalized, "受験日（終了）"),
    getValue_(normalized, "受験時間（終了）"),
  );

  return normalized;
}

function clearMappedCells_(sheet, cellMap) {
  [...new Set(Object.values(cellMap))].forEach((cell) => {
    sheet.getRange(cell).setValue("");
  });
}

function markMappedCell_(sheet, cellMap, value) {
  if (!value) return;

  const normalizedValue = value.toString().trim();
  if (!cellMap[normalizedValue]) return;

  sheet.getRange(cellMap[normalizedValue]).setValue("〇");
}

function getValue_(rowData, headerName) {
  if (rowData[headerName]) return rowData[headerName];

  const normalizedHeaderName = normalizeHeaderName_(headerName);
  if (rowData[normalizedHeaderName]) return rowData[normalizedHeaderName];

  const alias = getHeaderAlias_(normalizedHeaderName);
  return alias ? rowData[alias] || "" : "";
}

function getFirstValue_(rowData, headerNames) {
  for (const headerName of headerNames) {
    const value = getValue_(rowData, headerName);

    if (value) {
      return value;
    }
  }

  return "";
}

function normalizeHeaderName_(headerName) {
  return headerName
    .toString()
    .trim()
    .replace(/\[\]$/, "")
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

function getHeaderAlias_(normalizedHeaderName) {
  const aliases = {
    "就職試験の感想（試験内容）および後輩へのアドバイス ※簡潔にまとめて記述": "就職試験の感想および後輩へのアドバイス",
    "就職試験の感想および後輩へのアドバイス": "就職試験の感想（試験内容）および後輩へのアドバイス ※簡潔にまとめて記述",
  };

  return aliases[normalizedHeaderName] || "";
}

function joinDateTime_(dateValue, timeValue) {
  if (!dateValue && !timeValue) return "";
  return [dateValue, timeValue].filter(Boolean).join(" ");
}

function getExamDateText_(rowData) {
  const examDate = getValue_(rowData, "受験日（開始）") || getValue_(rowData, "受験日時（開始）");
  const formattedDate = formatDateValue_(examDate);

  if (!formattedDate) {
    return Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyyMMdd");
  }

  return formattedDate.toString().replace(/\//g, "");
}

function sanitizeFileName_(value) {
  return value
    ? value.toString().trim().replace(/[\\/:*?"<>|]/g, "_")
    : "未入力";
}

function formatDateValue_(value) {
  if (!value) return "";

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return Utilities.formatDate(
    date,
    Session.getScriptTimeZone(),
    "yyyy/MM/dd",
  );
}

function formatTimeValue_(value) {
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

function normalizeTeacherName_(teacherName) {
  if (!teacherName) return "";
  return teacherName.toString().trim().replace(/先生$/, "");
}
