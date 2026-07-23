    add_shortcode('exam_report_form', function () {
        $submit_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_report_submit'])) {
            $gas_url = 'https://script.google.com/macros/s/AKfycbw-z6zP0K774d9jFyxE5noYQcelOfNJxCGxe71C312EFEL-_49yl5yzfqt7KjKFdxxs6A/exec';

            $payload = exam_report_sanitize_post_data($_POST);
            unset($payload['exam_report_submit']);

            $request_args = [
                'headers' => [
                    'User-Agent' => 'WordPress Exam Report Form',
                ],
                'body' => [
                    'payload' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
                ],
                'timeout' => 30,
                'redirection' => 0,
            ];

            $response = wp_remote_post($gas_url, $request_args);
            $redirect_url = '';

            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                $location = wp_remote_retrieve_header($response, 'location');

                if ($code >= 300 && $code < 400 && $location) {
                    $redirect_url = $location;
                    $response = wp_remote_get($location, [
                        'headers' => [
                            'User-Agent' => 'WordPress Exam Report Form',
                        ],
                        'timeout' => 30,
                        'redirection' => 5,
                    ]);
                }
            }

            if (is_wp_error($response)) {
                $submit_message = '<div class="exam-submit-message error">送信失敗: WordPress HTTP Error<br>' . esc_html($response->get_error_message()) . '</div>';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);

                if ($code >= 200 && $code < 300) {
                    $decoded_body = json_decode($body, true);

                    if (is_array($decoded_body) && isset($decoded_body['ok']) && $decoded_body['ok'] === false) {
                        $submit_message = '<div class="exam-submit-message error">GAS処理失敗:<br>' . esc_html($body) . '</div>';
                    } else {
                        $submit_message = '<div class="exam-submit-message success">送信が完了しました。</div>';
                    }
                } else {
                    $headers = wp_remote_retrieve_headers($response);
                    $debug_url = $redirect_url ? $redirect_url : $gas_url;
                    $submit_message = '<div class="exam-submit-message error">送信失敗: HTTP ' . esc_html($code) . '<br><pre style="white-space:pre-wrap;">URL: ' . esc_html(mb_substr($debug_url, 0, 500)) . '\n\n' . esc_html(print_r($headers, true)) . '</pre><br>' . esc_html(mb_substr($body, 0, 1000)) . '</div>';
                }
            }
        }
        ob_start();
        ?>

        <style>
            .exam-report-form {
                max-width: 900px;
                margin: 0 auto;
                padding: 24px;
                border: 1px solid #d7dde8;
                border-radius: 12px;
                background: #f8fafc;
                box-shadow: 0 18px 45px rgba(30, 41, 59, 0.12);
                color: #172033;
                position: relative;
                overflow: hidden;
            }
            .exam-report-form fieldset {
                margin: 24px 0;
                padding: 22px;
                border: 1px solid #dbe2ec;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 8px 24px rgba(30, 41, 59, 0.06);
            }
            .exam-report-form legend {
                font-weight: bold;
                padding: 0 8px;
                color: #0f4c81;
            }
            .exam-report-form label {
                display: flex;
                align-items: center;
                gap: 7px;
                margin-top: 14px;
                font-weight: bold;
                color: #1f2937;
            }
            .exam-report-form input,
            .exam-report-form select,
            .exam-report-form textarea {
                width: 100%;
                padding: 11px 12px;
                margin-top: 6px;
                box-sizing: border-box;
                border: 1px solid #cfd8e5;
                border-radius: 7px;
                background: #fff;
                color: #172033;
                font-size: 15px;
                transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            }
            .exam-report-form textarea {
                min-height: 100px;
                resize: vertical;
            }
            .exam-report-form input:focus,
            .exam-report-form select:focus,
            .exam-report-form textarea:focus {
                outline: none;
                border-color: #1f78b4;
                box-shadow: 0 0 0 4px rgba(31, 120, 180, 0.14);
                background: #fbfdff;
            }
            .exam-report-form .radio-group label {
                font-weight: normal;
                display: inline-flex;
                margin-right: 18px;
            }
            .exam-report-form .radio-group input {
                width: auto;
                margin-right: 6px;
            }
            .exam-report-form .form-step,
            .exam-report-form .hidden-section {
                display: none;
            }
            .exam-report-form .form-step.active {
                display: block;
                animation: examStepSlideIn 0.34s cubic-bezier(0.22, 1, 0.36, 1);
            }
            .exam-report-form .form-step.slide-back.active {
                animation: examStepSlideBack 0.34s cubic-bezier(0.22, 1, 0.36, 1);
            }
            @keyframes examStepSlideIn {
                from {
                    opacity: 0;
                    transform: translateX(46px) scale(0.985);
                }
                to {
                    opacity: 1;
                    transform: translateX(0) scale(1);
                }
            }
            @keyframes examStepSlideBack {
                from {
                    opacity: 0;
                    transform: translateX(-46px) scale(0.985);
                }
                to {
                    opacity: 1;
                    transform: translateX(0) scale(1);
                }
            }
            .exam-report-form .step-nav,
            .exam-report-form .exam-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 24px;
                align-items: center;
            }
            .exam-report-form button {
                padding: 12px 28px;
                font-size: 16px;
                cursor: pointer;
                border: none;
                border-radius: 8px;
                background: #0f6ea8;
                color: #fff;
                font-weight: bold;
                transition: transform 0.16s ease, background 0.16s ease, box-shadow 0.16s ease;
                box-shadow: 0 8px 18px rgba(15, 110, 168, 0.25);
            }
            .exam-report-form button:hover {
                background: #0b5c8d;
                transform: translateY(-2px);
                box-shadow: 0 12px 24px rgba(15, 110, 168, 0.28);
            }
            .exam-report-form button:active {
                transform: translateY(0);
                box-shadow: 0 5px 12px rgba(15, 110, 168, 0.22);
            }
            .exam-report-form button:disabled {
                cursor: wait;
                opacity: 0.72;
                transform: none;
            }
            .exam-report-form .secondary-button {
                background: #eef3f8;
                color: #164b73;
                border: 1px solid #bed0df;
                box-shadow: 0 5px 14px rgba(30, 41, 59, 0.08);
            }
            .exam-report-form .secondary-button:hover {
                background: #e1ebf3;
                color: #0f3f64;
            }
            .exam-report-form .choice-forward-button {
                background: #164b73;
                color: #fff;
                border-color: #164b73;
                min-width: 220px;
                padding: 15px 34px;
                font-size: 17px;
                box-shadow: 0 8px 18px rgba(22, 75, 115, 0.22);
            }
            .exam-report-form .choice-forward-button:hover {
                background: #0f3f64;
                color: #fff;
            }
            .exam-report-form .exam-picker-panel {
                margin: 24px 0;
                padding: 18px;
                border: 1px solid #dbe2ec;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 10px 26px rgba(30, 41, 59, 0.07);
            }
            .exam-report-form .exam-picker-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 14px;
            }
            .exam-report-form .exam-picker-title {
                font-weight: bold;
                color: #0f4c81;
            }
            .exam-report-form .exam-type-menu {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 10px;
            }
            .exam-report-form .exam-type-button {
                min-height: 58px;
                padding: 12px 10px;
                border: 1px solid #c9d8e6;
                background: #f7fbff;
                color: #164b73;
                box-shadow: none;
                font-size: 14px;
                line-height: 1.3;
            }
            .exam-report-form .exam-type-button:hover {
                background: #e7f3fb;
                color: #0f3f64;
                box-shadow: 0 8px 18px rgba(15, 110, 168, 0.12);
            }
            .exam-report-form .exam-type-button.is-selected {
                border-color: #0f6ea8;
                background: #e7f3fb;
                color: #0f3f64;
                box-shadow: inset 0 0 0 1px rgba(15, 110, 168, 0.2);
            }
            .exam-report-form .exam-type-button.is-selected::after {
                content: "選択済み";
                display: block;
                margin-top: 5px;
                color: #64748b;
                font-size: 11px;
                font-weight: bold;
            }
            .exam-report-form .exam-type-button.is-complete {
                border-color: #25a36f;
                background: #e8f6ee;
                color: #116b35;
            }
            .exam-report-form .exam-type-button.is-complete::after {
                content: "✓ 完了";
                color: #116b35;
            }
            .exam-report-form .exam-picker-actions {
                display: none;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 16px;
                align-items: center;
            }
            .exam-report-form .exam-picker-add {
                display: none;
                background: #0f6ea8;
                box-shadow: 0 8px 18px rgba(15, 110, 168, 0.22);
            }
            .exam-report-form .exam-picker-add:hover {
                background: #0b5c8d;
                box-shadow: 0 12px 24px rgba(15, 110, 168, 0.26);
            }
            .exam-report-form .exam-picker-next {
                display: none;
                min-width: 170px;
                padding: 14px 28px;
                background: #25a36f;
                box-shadow: 0 8px 18px rgba(37, 163, 111, 0.22);
            }
            .exam-report-form .exam-picker-next:hover {
                background: #1d8a5d;
                box-shadow: 0 12px 24px rgba(37, 163, 111, 0.26);
            }
            .exam-report-form.has-exam-cards .exam-picker-actions {
                display: flex;
            }
            .exam-report-form.has-exam-cards .exam-picker-add,
            .exam-report-form.has-exam-cards .exam-picker-next {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .exam-report-form.has-exam-cards.is-choosing-exam .exam-picker-add {
                display: none;
            }
            .exam-report-form.has-exam-cards:not(.is-choosing-exam) .exam-picker-head,
            .exam-report-form.has-exam-cards:not(.is-choosing-exam) .exam-type-menu {
                display: none;
            }
            .exam-report-form .exam-card {
                margin: 24px 0;
                padding: 22px;
                border: 1px solid #d7dde8;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 10px 26px rgba(30, 41, 59, 0.08);
                animation: examCardIn 0.26s ease-out;
            }
            .exam-report-form .revealed-section {
                animation: examRevealDown 0.24s ease-out;
            }
            .exam-report-form .exam-card-title {
                font-weight: bold;
                margin-bottom: 12px;
                color: #0f4c81;
            }
            .exam-report-form .step-indicator {
                margin-bottom: 18px;
                font-weight: bold;
                color: #164b73;
                letter-spacing: 0;
            }
            .exam-report-form .progress-shell {
                margin-bottom: 24px;
                padding: 14px 16px;
                border: 1px solid #dbe2ec;
                border-radius: 10px;
                background: #fff;
                box-shadow: 0 8px 22px rgba(30, 41, 59, 0.06);
            }
            .exam-report-form .progress-meta {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 10px;
                color: #334155;
                font-weight: bold;
                font-size: 14px;
            }
            .exam-report-form .progress-track {
                height: 9px;
                overflow: hidden;
                border-radius: 999px;
                background: #e7edf4;
            }
            .exam-report-form .progress-fill {
                display: block;
                height: 100%;
                width: 20%;
                border-radius: inherit;
                background: linear-gradient(90deg, #0f6ea8, #25a36f);
                transition: width 0.34s ease;
            }
            .exam-report-form .datetime-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .exam-report-form .datetime-period-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 14px;
            }
            .exam-report-form .datetime-period {
                display: grid;
                grid-template-columns: minmax(72px, auto) minmax(0, 1fr) minmax(0, 1fr);
                gap: 10px;
                align-items: end;
            }
            .exam-report-form .datetime-period-title {
                padding-bottom: 12px;
                font-weight: bold;
                color: #164b73;
                white-space: nowrap;
            }
            .exam-report-form .compact-row {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
                align-items: end;
            }
            .exam-report-form .compact-row.four-columns {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
            .exam-report-form .compact-row input,
            .exam-report-form .unit-input input {
                max-width: 150px;
            }
            .exam-report-form .unit-input {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .exam-report-form .input-unit {
                margin-top: 6px;
                font-weight: bold;
                color: #334155;
            }
            .exam-report-form .inline-radio-group {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 8px;
            }
            .exam-report-form .inline-radio-group label {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                margin-top: 0;
                padding: 9px 12px;
                border: 1px solid #cfd8e5;
                border-radius: 7px;
                background: #f8fafc;
                font-weight: bold;
                color: #334155;
            }
            .exam-report-form .inline-radio-group input {
                width: auto;
                margin-top: 0;
            }
            .exam-report-form .form-subtitle {
                margin: 22px 0 10px;
                padding-top: 14px;
                border-top: 1px solid #e2e8f0;
                color: #164b73;
                font-size: 15px;
                font-weight: bold;
            }
            .exam-report-form .qa-pair {
                display: grid;
                grid-template-columns: minmax(0, 0.82fr) minmax(0, 1.18fr);
                gap: 12px;
                align-items: start;
                margin-top: 12px;
            }
            .exam-report-form .qa-pair label {
                margin-top: 0;
            }
            .exam-report-form .soft-label {
                color: #64748b;
                font-weight: 800;
            }
            .exam-report-form .interview-question-controls {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                align-items: center;
                margin-top: 14px;
                padding-top: 12px;
                border-top: 1px solid #e2e8f0;
            }
            .exam-report-form .interview-question-prompt {
                width: 100%;
                color: #164b73;
                font-weight: bold;
            }
            .exam-report-form .interview-question-note {
                width: 100%;
                color: #64748b;
                font-size: 13px;
                font-weight: bold;
                line-height: 1.45;
            }
            .exam-report-form .remove-interview-question {
                align-self: end;
                padding: 10px 16px;
                background: #eef3f8;
                color: #164b73;
                border: 1px solid #bed0df;
                box-shadow: none;
            }
            .exam-report-form .char-counter {
                margin-top: 5px;
                text-align: right;
                color: #64748b;
                font-size: 12px;
                font-weight: bold;
                line-height: 1;
            }
            .exam-report-form .char-counter.is-limit {
                color: #b91c1c;
            }
            .exam-report-form .long-report-textarea {
                min-height: 560px;
                padding: 18px 20px;
                font-size: 16px;
                line-height: 1.85;
                letter-spacing: 0;
                white-space: pre-wrap;
                font-family: inherit;
            }
            @media (max-width: 600px) {
                .exam-report-form {
                    padding: 16px;
                }
                .exam-report-form .long-report-textarea {
                    min-height: 440px;
                    padding: 15px;
                    font-size: 15px;
                    line-height: 1.75;
                }
                .exam-report-form .datetime-row,
                .exam-report-form .datetime-period-row,
                .exam-report-form .datetime-period,
                .exam-report-form .compact-row.four-columns,
                .exam-report-form .compact-row,
                .exam-report-form .qa-pair {
                    grid-template-columns: 1fr;
                }
                .exam-report-form .interview-question-controls {
                    align-items: stretch;
                    flex-direction: column;
                }
                .exam-report-form .compact-row input,
                .exam-report-form .unit-input input {
                    max-width: 100%;
                }
                .exam-report-form .datetime-period-title {
                    padding-bottom: 0;
                }
                .exam-report-form .exam-picker-head {
                    align-items: stretch;
                    flex-direction: column;
                }
                .exam-report-form .exam-type-menu {
                    grid-template-columns: 1fr 1fr;
                }
                .exam-report-form .exam-picker-next {
                    width: 100%;
                }
                .exam-report-form .exam-picker-add {
                    width: 100%;
                }
            }
            .exam-report-form .confirm-list {
                margin-top: 16px;
                padding: 16px;
                border: 1px solid #ddd;
                border-radius: 8px;
                background: #fff;
                overflow-x: auto;
            }
            .exam-report-form .confirm-table {
                width: 100%;
                min-width: 620px;
                border-collapse: collapse;
            }
            .exam-report-form .confirm-table th,
            .exam-report-form .confirm-table td {
                padding: 12px 14px;
                border-bottom: 1px solid #e2e8f0;
                text-align: left;
                vertical-align: top;
            }
            .exam-report-form .confirm-table tr:last-child th,
            .exam-report-form .confirm-table tr:last-child td {
                border-bottom: none;
            }
            .exam-report-form .confirm-table th {
                width: 34%;
                background: #f1f6fb;
                color: #164b73;
                font-weight: bold;
            }
            .exam-report-form .confirm-table td {
                white-space: pre-wrap;
                color: #172033;
            }
            .exam-report-form .help-tip {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 auto;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: #e8f2fb;
                color: #0f6ea8;
                border: 1px solid #b8d7ec;
                font-size: 12px;
                line-height: 1;
                cursor: help;
            }
            .exam-report-form .help-tip::after {
                content: attr(data-tip);
                position: absolute;
                left: 50%;
                bottom: calc(100% + 8px);
                z-index: 10;
                width: max-content;
                max-width: 260px;
                padding: 8px 10px;
                border-radius: 7px;
                background: #172033;
                color: #fff;
                font-size: 12px;
                font-weight: normal;
                line-height: 1.45;
                opacity: 0;
                pointer-events: none;
                transform: translate(-50%, 5px);
                transition: opacity 0.16s ease, transform 0.16s ease;
                white-space: normal;
            }
            .exam-report-form .help-tip:hover::after,
            .exam-report-form .help-tip:focus::after {
                opacity: 1;
                transform: translate(-50%, 0);
            }
            .exam-report-form.is-submitting .exam-submit-overlay {
                display: flex;
            }
            .exam-submit-overlay {
                position: fixed;
                inset: 0;
                z-index: 30;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 18px;
                background: rgba(15, 23, 42, 0.34);
                backdrop-filter: blur(3px);
            }
            .exam-submit-panel {
                width: min(520px, 100%);
                padding: 40px;
                border-radius: 16px;
                background: #fff;
                text-align: center;
                box-shadow: 0 18px 45px rgba(30, 41, 59, 0.18);
                color: #172033;
                font-weight: bold;
                font-size: 18px;
                animation: examSubmitPanelIn 0.22s ease-out;
            }
            .exam-submit-panel.success {
                color: #116b35;
            }
            .exam-submit-panel.error {
                color: #9b1c1c;
            }
            .exam-spinner {
                width: 64px;
                height: 64px;
                margin: 0 auto 20px;
                border: 7px solid #dbe8f2;
                border-top-color: #0f6ea8;
                border-radius: 50%;
                animation: examSpin 0.8s linear infinite;
            }
            .exam-submit-check {
                display: none;
                align-items: center;
                justify-content: center;
                width: 72px;
                height: 72px;
                margin: 0 auto 20px;
                border-radius: 50%;
                background: #25a36f;
                color: #fff;
                font-size: 42px;
                animation: examCheckPop 0.34s ease-out;
            }
            .exam-submit-overlay.is-success .exam-spinner,
            .exam-submit-overlay.is-error .exam-spinner {
                display: none;
            }
            .exam-submit-overlay.is-success .exam-submit-check {
                display: inline-flex;
            }
            @keyframes examSpin {
                to {
                    transform: rotate(360deg);
                }
            }
            @keyframes examSubmitPanelIn {
                from {
                    opacity: 0;
                    transform: translateY(8px) scale(0.96);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            @keyframes examCardIn {
                from {
                    opacity: 0;
                    transform: translateY(12px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            @keyframes examRevealDown {
                from {
                    opacity: 0;
                    transform: translateY(-8px);
                    max-height: 0;
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                    max-height: 900px;
                }
            }
            .exam-submit-message {
                max-width: 900px;
                margin: 16px auto;
                padding: 14px 18px;
                border-radius: 8px;
                font-weight: bold;
                animation: examCardIn 0.3s ease-out;
            }
            .exam-submit-message.success::before {
                content: "✓";
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                margin-right: 9px;
                border-radius: 50%;
                background: #25a36f;
                color: #fff;
                animation: examCheckPop 0.34s ease-out;
            }
            .exam-submit-message.success {
                background: #e8f6ee;
                color: #116b35;
                border: 1px solid #9ad4b0;
            }
            .exam-submit-message.error {
                background: #fdecec;
                color: #9b1c1c;
                border: 1px solid #f3aaaa;
            }
            .exam-form-version {
                margin-top: 12px;
                text-align: right;
                color: #94a3b8;
                font-size: 11px;
                line-height: 1;
            }
            .exam-report-form .field-note {
                margin-top: 7px;
                color: #64748b;
                font-size: 13px;
                font-weight: bold;
                line-height: 1.45;
            }
            @keyframes examCheckPop {
                from {
                    transform: scale(0.55);
                    opacity: 0;
                }
                to {
                    transform: scale(1);
                    opacity: 1;
                }
            }
        </style>

        <?php echo $submit_message; ?>

        <form class="exam-report-form" method="post">
            <div class="progress-shell" aria-label="入力進捗">
                <div class="progress-meta">
                    <span id="progress_step_text">STEP 1 / 5</span>
                    <span id="progress_percent_text">20%</span>
                </div>
                <div class="progress-track" aria-hidden="true">
                    <span class="progress-fill" id="progress_fill"></span>
                </div>
            </div>

            <section class="form-step active" data-step="1">
                <div class="step-indicator">STEP 1 / 5　基本情報</div>

                <fieldset>
                    <legend>基本情報</legend>

                    <label>学籍番号</label>
                    <input type="text" id="student_number" name="学籍番号" inputmode="numeric" pattern="[0-9]{7}" maxlength="7" title="7桁の半角数字で入力してください" autocomplete="off">

                    <label>名前</label>
                    <input type="text" name="名前" placeholder="例：山田 太郎">

                    <label>メールアドレス</label>
                    <input type="email" name="メールアドレス" placeholder="例：student@example.com">

                    <label>学校名</label>
                    <select id="school_name" name="学校名">
                        <option value="">選択してください</option>
                        <option>広島コンピュータ専門学校</option>
                        <option>広島会計学院ビジネス専門学校</option>
                        <option>広島外語専門学校</option>
                        <option>広島美容専門学校</option>
                        <option>広島情報ビジネス専門学校</option>
                        <option>広島公務員専門学校</option>
                    </select>

                    <div id="hirocon_only_fields" class="hidden-section">
                        <label>コース名</label>
                        <select id="course_name_select" name="コース名">
                            <option value="">選択してください</option>
                            <option>システムエンジニア</option>
                            <option>ネットワークエンジニア</option>
                            <option>ゲームクリエーター</option>
                            <option>情報処理プログラム</option>
                            <option>オフィスビジネス</option>
                            <option>webデザイン</option>
                            <option>ゲームCG</option>
                            <option>ネットワークセキュリティ</option>
                            <option>イラスト</option>
                            <option>グラフィックデザイン</option>
                            <option>音響技術</option>
                            <option>CGクリエーター</option>
                        </select>

                        <label>担任</label>
                        <select id="teacher_name_select" name="担任">
                            <option value="">選択してください</option>
                            <option>山岡</option>
                            <option>森本</option>
                            <option>南</option>
                            <option>シゴレフ</option>
                            <option>竹田</option>
                            <option>新川</option>
                        </select>
                    </div>

                    <div id="other_school_fields" class="hidden-section">
                        <label>コース名</label>
                        <select id="hiroka_course_name_select" name="コース名" style="display: none;">
                            <option value="">選択してください</option>
                            <option>医療事務コース</option>
                            <option>フラワーデザイナーコース</option>
                            <option>経理財務コース</option>
                            <option>税理士マスターコース</option>
                            <option>税理士コース</option>
                        </select>
                        <select id="gaigo_course_name_select" name="コース名" style="display: none;">
                            <option value="">選択してください</option>
                            <option>総合英語コース</option>
                            <option>海外留学コース</option>
                            <option>国内大学編入コース</option>
                            <option>エアラインコース</option>
                            <option>ホテルコース</option>
                            <option>国際ビジネスコース</option>
                        </select>
                        <select id="biyo_course_name_select" name="コース名" style="display: none;">
                            <option value="">選択してください</option>
                            <option>美容科</option>
                            <option>トータルビューティ科 | ヘアメイクコース</option>
                            <option>トータルビューティ科 | メイクアップコース</option>
                            <option>トータルビューティ科 | ネイルコース</option>
                            <option>トータルビューティ科 | エステティックコース</option>
                        </select>
                        <select id="hjb_course_name_select" name="コース名" style="display: none;">
                            <option value="">選択してください</option>
                            <option>医療秘書コース</option>
                            <option>ブライダルコーディネーターコース</option>
                            <option>ペットビジネスコース</option>
                            <option>ペットケア＆トレーニングコース</option>
                            <option>情報ビジネスコース</option>
                            <option>販売ビジネスコース</option>
                        </select>
                        <select id="uhk_course_name_select" name="コース名" style="display: none;">
                            <option value="">選択してください</option>
                            <option>公務員科</option>
                            <option>公務員速成科</option>
                        </select>

                        <label>担任</label>
                        <input type="text" id="teacher_name_text" name="担任">
                    </div>

                    <label>性別</label>
                    <div class="radio-group">
                        <label><input type="radio" name="性別" value="男">男</label>
                        <label><input type="radio" name="性別" value="女">女</label>
                    </div>
                </fieldset>

                <div class="step-nav">
                    <button type="button" data-next-step="2">次へ</button>
                </div>
            </section>

            <section class="form-step" data-step="2">
                <div class="step-indicator">STEP 2 / 5　受験情報</div>

                <fieldset>
                    <legend>受験情報</legend>

                    <label>受験先企業名（正式名称）</label>
                    <input type="text" name="受験先企業名">

                    <label>本社（所在地）</label>
                    <input type="text" name="本社">

                    <div class="datetime-period-row">
                        <div class="datetime-period">
                            <div class="datetime-period-title">開始時日</div>
                            <div>
                                <label>日付</label>
                                <input type="date" name="受験日（開始）">
                            </div>
                            <div>
                                <label>時間</label>
                                <input type="time" name="受験時間（開始）">
                            </div>
                        </div>
                        <div class="datetime-period">
                            <div class="datetime-period-title">終了時日</div>
                            <div>
                                <label>日付</label>
                                <input type="date" name="受験日（終了）">
                            </div>
                            <div>
                                <label>時間</label>
                                <input type="time" name="受験時間（終了）">
                            </div>
                        </div>
                    </div>

                    <label>受験場所</label>
                    <div class="radio-group">
                        <label><input type="radio" name="受験場所" value="本社">本社</label>
                        <label><input type="radio" name="受験場所" value="Ｗｅｂ（オンライン）">Ｗｅｂ（オンライン）</label>
                    </div>

                    <label>業種名</label>
                    <input type="text" name="業種名">

                    <label>応募職種 職種名</label>
                    <input type="text" name="職種名">

                    <label>応募方法</label>
                    <select id="apply_method" name="応募方法">
                        <option value="">選択してください</option>
                        <option>学校求人</option>
                        <option>縁故</option>
                        <option>自己開拓</option>
                        <option>ハローワーク</option>
                        <option>就職サイト／会社ＨＰ</option>
                        <option>その他（エージェント等）</option>
                    </select>

                    <div id="job_site_section" class="hidden-section">
                        <label>利用した就職サイト／会社ＨＰを記入してください。</label>
                        <select name="利用した就職サイト／会社ＨＰを記入してください。">
                            <option value="">選択してください</option>
                            <option>リクルートナビ</option>
                            <option>マイナビ</option>
                            <option>ＪｏｂＷａｙ</option>
                            <option>Indeed(インディード)</option>
                            <option>キャリタス就活</option>
                        </select>
                    </div>

                    <div id="agent_section" class="hidden-section">
                        <label>その他利用したエージェント等を記入してください。</label>
                        <textarea name="その他利用したエージェント等を記入してください。"></textarea>
                    </div>
                </fieldset>

                <div class="step-nav">
                    <button type="button" class="secondary-button" data-prev-step="1">戻る</button>
                    <button type="button" data-next-step="3">次へ</button>
                </div>
            </section>

            <section class="form-step" data-step="3">
                <div class="step-indicator">STEP 3 / 5　試験内容を選択・入力</div>

                <div id="exam_sections_container"></div>

                <div id="exam_picker_panel" class="exam-picker-panel">
                    <div class="exam-picker-head">
                        <div class="exam-picker-title">試験内容を選んでください</div>
                    </div>
                    <div class="exam-type-menu" aria-label="試験内容メニュー">
                        <button type="button" class="exam-type-button" data-exam-type="面接試験">面接試験</button>
                        <button type="button" class="exam-type-button" data-exam-type="筆記試験">筆記試験</button>
                        <button type="button" class="exam-type-button" data-exam-type="グループディスカッション">グループディスカッション</button>
                        <button type="button" class="exam-type-button" data-exam-type="作文試験">作文試験</button>
                        <button type="button" class="exam-type-button" data-exam-type="適性検査">適性検査</button>
                    </div>
                    <div class="exam-picker-actions">
                        <button type="button" id="add_more_exam_input" class="exam-picker-add">他の試験入力</button>
                        <button type="button" id="finish_exam_input" class="exam-picker-next">次へ進む</button>
                    </div>
                </div>

                <div class="step-nav">
                    <button type="button" class="secondary-button" data-prev-step="2">戻る</button>
                </div>
            </section>

            <section class="form-step" data-step="4">
                <div class="step-indicator">STEP 4 / 5　試験全般</div>

                <fieldset>
                    <legend>試験全般について</legend>

                    <label data-enhanced="true">就職試験全般の感想(試験内容)および後輩へのアドバイス</label>
                    <textarea class="long-report-textarea" name="就職試験の感想および後輩へのアドバイス" maxlength="1800" placeholder="残り文字数を参考に、できるだけ詳しく記入してください"></textarea>
                </fieldset>

                <div class="step-nav">
                    <button type="button" class="secondary-button" data-prev-step="3">戻る</button>
                    <button type="button" id="go_confirm_step">確認へ進む</button>
                </div>
            </section>

            <section class="form-step" data-step="5">
                <div class="step-indicator">STEP 5 / 5　確認・送信</div>

                <fieldset>
                    <legend>入力内容確認</legend>
                    <div id="confirm_output" class="confirm-list"></div>
                </fieldset>

                <div class="step-nav">
                    <button type="button" class="secondary-button" data-prev-step="4">戻る</button>
                    <button type="submit" name="exam_report_submit">送信</button>
                </div>
            </section>

            <div class="exam-submit-overlay" role="status" aria-live="polite">
                <div class="exam-submit-panel">
                    <div class="exam-spinner" aria-hidden="true"></div>
                    <div class="exam-submit-check" aria-hidden="true">✓</div>
                    <div class="exam-submit-status">フォームを送信しています。<br>少々お待ちください。</div>
                </div>
            </div>

            <div class="exam-form-version">シゴレフ　エドワード　ver. 1.1</div>
        </form>

        <template id="template_面接試験">
            <div class="exam-card" data-exam-card="面接試験">
                <div class="exam-card-title">面接試験について</div>
                <input type="hidden" name="就職採用試験選択[]" value="面接試験">

                <label>今回の面接試験は何次試験ですか？</label>
                <div class="inline-radio-group" data-radio-array-name="今回の面接試験は何次試験ですか？[]">
                    <label><input type="radio" value="1次">1次</label>
                    <label><input type="radio" value="2次">2次</label>
                    <label><input type="radio" value="3次">3次</label>
                    <label><input type="radio" value="最終">最終</label>
                </div>

                <label>面接方法について</label>
                <div class="inline-radio-group" data-radio-array-name="面接方法について[]">
                    <label><input type="radio" value="オンライン面接">オンライン面接</label>
                    <label><input type="radio" value="対面（個人面接）">対面（個人面接）</label>
                    <label><input type="radio" value="対面（集団面接）">対面（集団面接）</label>
                </div>

                <div class="compact-row">
                    <div>
                        <label>面接時間</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="面接時間（約何分）[]">
                            <span class="input-unit">分</span>
                        </div>
                    </div>
                    <div>
                        <label>面接官の人数</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="面接官の人数[]">
                            <span class="input-unit">人</span>
                        </div>
                    </div>
                    <div>
                        <label>受験人数</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="受験人数（およそで構いません）[]">
                            <span class="input-unit">人</span>
                        </div>
                    </div>
                </div>

                <div class="form-subtitle">質問項目</div>

                <label>志望動機</label>
                <textarea name="②質問に対してどのように返答したか？＜志望動機について＞※簡潔にまとめて記述" maxlength="210" placeholder="質問に対してどのように返答したか？"></textarea>

                <label>自己ＰＲ</label>
                <textarea name="②質問に対してどのように返答したか？＜自己ＰＲについて＞※簡潔にまとめて記述" maxlength="210" placeholder="質問に対してどのように返答したか？"></textarea>

                <div class="interview-extra-questions"></div>
                <div class="interview-question-controls">
                    <div class="interview-question-prompt">その他の質問はありましたか？</div>
                    <div class="interview-question-note">可能な範囲で追加の質問を入力してください。</div>
                    <button type="button" class="add-interview-question">追加する</button>
                </div>

                <label>面接試験内容補足（書き方自由）</label>
                <textarea name="面接試験内容補足（書き方自由）[]" maxlength="190" placeholder="残り文字数を参考に、できるだけ詳しく記入してください"></textarea>
                <div class="field-note">後輩へのアドバイスは次ページの専用欄にご記入ください。</div>

                <div class="exam-actions">
                    <button type="button" class="secondary-button remove-exam-card">この試験内容を削除</button>
                </div>
            </div>
        </template>

        <template id="template_グループディスカッション">
            <div class="exam-card" data-exam-card="グループディスカッション">
                <div class="exam-card-title">グループディスカッションについて</div>
                <input type="hidden" name="就職採用試験選択[]" value="グループディスカッション">

                <div class="compact-row four-columns">
                    <div>
                        <label>グループディスカッション実施時間</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="グループディスカッション実施時間（約何分）[]">
                            <span class="input-unit">分</span>
                        </div>
                    </div>
                    <div>
                        <label>ディスカッション試験管人数</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="ディスカッション試験管人数[]">
                            <span class="input-unit">人</span>
                        </div>
                    </div>
                    <div>
                        <label>グループ人数</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="グループ人数[]">
                            <span class="input-unit">人</span>
                        </div>
                    </div>
                    <div>
                        <label>グループ数</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="グループ数[]">
                            <span class="input-unit">組</span>
                        </div>
                    </div>
                </div>

                <label>テーマ</label>
                <input type="text" name="テーマ[]" maxlength="48">

                <label>実施感想および気づき</label>
                <textarea name="実施感想および気づき[]" maxlength="48"></textarea>

                <div class="exam-actions">
                    <button type="button" class="secondary-button remove-exam-card">この試験内容を削除</button>
                </div>
            </div>
        </template>

        <template id="template_筆記試験">
            <div class="exam-card" data-exam-card="筆記試験">
                <div class="exam-card-title">筆記試験</div>
                <input type="hidden" name="就職採用試験選択[]" value="筆記試験">

                <label>筆記試験時間</label>
                <div class="unit-input">
                    <input type="number" min="0" inputmode="numeric" name="筆記試験時間（単位：約何分）[]">
                    <span class="input-unit">分</span>
                </div>

                <label>問題など具体的に記述してください。</label>
                <textarea name="筆記試験　問題など具体的に記述してください。[]" maxlength="120"></textarea>

                <div class="exam-actions">
                    <button type="button" class="secondary-button remove-exam-card">この試験内容を削除</button>
                </div>
            </div>
        </template>

        <template id="template_適性検査">
            <div class="exam-card" data-exam-card="適性検査">
                <div class="exam-card-title">適性検査について</div>
                <input type="hidden" name="就職採用試験選択[]" value="適性検査">

                <label>適性検査時間</label>
                <div class="unit-input">
                    <input type="number" min="0" inputmode="numeric" name="適性検査時間（単位：分）[]">
                    <span class="input-unit">分</span>
                </div>

                <label>試験実施方法</label>
                <div class="inline-radio-group" data-radio-array-name="試験実施方法[]">
                    <label><input type="radio" value="Ｗｅｂ（オンライン）">Ｗｅｂ（オンライン）</label>
                    <label><input type="radio" value="ペーパーベース">ペーパーベース</label>
                </div>

                <label>適性検査種類</label>
                <select name="適性検査種類[]">
                    <option value="">選択してください</option>
                    <option>ＳＰＩ</option>
                    <option>Ｖ－ＣＡＴ</option>
                    <option>クレペリン</option>
                    <option>Ｃｕｂｉｃ</option>
                    <option>ＧＡＢ・ＣＡＢ</option>
                </select>

                <div class="exam-actions">
                    <button type="button" class="secondary-button remove-exam-card">この試験内容を削除</button>
                </div>
            </div>
        </template>

        <template id="template_作文試験">
            <div class="exam-card" data-exam-card="作文試験">
                <div class="exam-card-title">作文試験</div>
                <input type="hidden" name="就職採用試験選択[]" value="作文試験">

                <div class="compact-row">
                    <div>
                        <label>作文試験時間</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="作文試験時間（単位：約何分）[]">
                            <span class="input-unit">分</span>
                        </div>
                    </div>
                    <div>
                        <label>枚数</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="原稿用紙・レポート用紙　枚数[]">
                            <span class="input-unit">枚</span>
                        </div>
                    </div>
                    <div>
                        <label>文字数</label>
                        <div class="unit-input">
                            <input type="number" min="0" inputmode="numeric" name="作文試験　文字数[]" placeholder="000">
                            <span class="input-unit">字</span>
                        </div>
                    </div>
                </div>

                <label>テーマ</label>
                <input type="text" name="作文試験　テーマ[]" maxlength="48">

                <div class="exam-actions">
                    <button type="button" class="secondary-button remove-exam-card">この試験内容を削除</button>
                </div>
            </div>
        </template>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('.exam-report-form');
            const steps = document.querySelectorAll('.form-step');
            const studentNumber = document.getElementById('student_number');
            const schoolName = document.getElementById('school_name');
            const hiroconOnlyFields = document.getElementById('hirocon_only_fields');
            const otherSchoolFields = document.getElementById('other_school_fields');
            const courseNameSelect = document.getElementById('course_name_select');
            const hirokaCourseNameSelect = document.getElementById('hiroka_course_name_select');
            const gaigoCourseNameSelect = document.getElementById('gaigo_course_name_select');
            const biyoCourseNameSelect = document.getElementById('biyo_course_name_select');
            const hjbCourseNameSelect = document.getElementById('hjb_course_name_select');
            const uhkCourseNameSelect = document.getElementById('uhk_course_name_select');
            const teacherNameSelect = document.getElementById('teacher_name_select');
            const teacherNameText = document.getElementById('teacher_name_text');
            const applyMethod = document.getElementById('apply_method');
            const jobSiteSection = document.getElementById('job_site_section');
            const agentSection = document.getElementById('agent_section');
            const examTypeButtons = document.querySelectorAll('[data-exam-type]');
            const addMoreExamInputButton = document.getElementById('add_more_exam_input');
            const finishExamInputButton = document.getElementById('finish_exam_input');
            const goConfirmStepButton = document.getElementById('go_confirm_step');
            const confirmOutput = document.getElementById('confirm_output');
            const examSectionsContainer = document.getElementById('exam_sections_container');
            const progressFill = document.getElementById('progress_fill');
            const progressStepText = document.getElementById('progress_step_text');
            const progressPercentText = document.getElementById('progress_percent_text');
            const submitOverlay = form.querySelector('.exam-submit-overlay');
            const submitPanel = form.querySelector('.exam-submit-panel');
            const submitStatus = form.querySelector('.exam-submit-status');

            let currentStepNumber = 1;
            let examCardSequence = 0;
            let isChoosingExam = true;
            const totalSteps = steps.length;

            function showStep(stepNumber) {
                const nextStepNumber = Number(stepNumber);
                const isBack = nextStepNumber < currentStepNumber;

                steps.forEach(function (step) {
                    step.classList.remove('active', 'slide-back');

                    if (step.dataset.step === String(stepNumber)) {
                        if (isBack) {
                            step.classList.add('slide-back');
                        }
                        step.classList.add('active');
                    }
                });

                currentStepNumber = nextStepNumber;
                updateProgress();
                window.scrollTo({ top: form.offsetTop, behavior: 'smooth' });
            }

            function updateProgress() {
                const percent = Math.round((currentStepNumber / totalSteps) * 100);

                progressFill.style.width = percent + '%';
                progressStepText.textContent = 'STEP ' + currentStepNumber + ' / ' + totalSteps;
                progressPercentText.textContent = percent + '%';
            }

            function validateCurrentStep(currentStep) {
                const activeStep = document.querySelector('.form-step[data-step="' + currentStep + '"]');
                const requiredFields = activeStep.querySelectorAll('[required]');

                for (const field of requiredFields) {
                    if (!field.checkValidity()) {
                        field.reportValidity();
                        return false;
                    }
                }

                return true;
            }

            function updateApplyMethod() {
                const showsJobSite = applyMethod.value === '就職サイト／会社ＨＰ';
                const showsAgent = applyMethod.value === 'その他（エージェント等）';

                setSectionVisibility(jobSiteSection, showsJobSite);
                setSectionVisibility(agentSection, showsAgent);
            }

            function setSectionVisibility(section, isVisible) {
                section.style.display = isVisible ? 'block' : 'none';
                section.classList.toggle('revealed-section', isVisible);

                section.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = !isVisible;
                    field.required = isVisible;

                    if (!isVisible) {
                        field.value = '';
                    }
                });
            }

            function keepDigitsOnly(field, maxLength) {
                const digits = field.value.replace(/\D/g, '').slice(0, maxLength);

                if (field.value !== digits) {
                    field.value = digits;
                }
            }

            function configureSchoolCourseSelect(select, isVisible) {
                select.required = isVisible;
                select.disabled = !isVisible;
                select.style.display = isVisible ? '' : 'none';

                if (!isVisible) {
                    select.value = '';
                }
            }

            function updateHiroconFields() {
                const isHirocon = schoolName.value === '広島コンピュータ専門学校';
                const isHiroka = schoolName.value === '広島会計学院ビジネス専門学校';
                const isGaigo = schoolName.value === '広島外語専門学校';
                const isBiyo = schoolName.value === '広島美容専門学校';
                const isHjb = schoolName.value === '広島情報ビジネス専門学校';
                const isUhk = schoolName.value === '広島公務員専門学校';

                setSectionVisibility(hiroconOnlyFields, isHirocon);
                setSectionVisibility(otherSchoolFields, isHirocon ? false : Boolean(schoolName.value));

                courseNameSelect.required = isHirocon;
                teacherNameSelect.required = isHirocon;
                courseNameSelect.disabled = !isHirocon;
                teacherNameSelect.disabled = !isHirocon;

                configureSchoolCourseSelect(hirokaCourseNameSelect, isHiroka);
                configureSchoolCourseSelect(gaigoCourseNameSelect, isGaigo);
                configureSchoolCourseSelect(biyoCourseNameSelect, isBiyo);
                configureSchoolCourseSelect(hjbCourseNameSelect, isHjb);
                configureSchoolCourseSelect(uhkCourseNameSelect, isUhk);

                teacherNameText.required = !isHirocon;
                teacherNameText.disabled = isHirocon;

                if (isHirocon) {
                    teacherNameText.value = '';
                } else {
                    courseNameSelect.value = '';
                    teacherNameSelect.value = '';
                }
            }

            function addExamSection(examType) {
                const existingCard = findExamCard(examType);

                if (existingCard) {
                    existingCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    existingCard.classList.remove('revealed-section');
                    void existingCard.offsetWidth;
                    existingCard.classList.add('revealed-section');
                    isChoosingExam = false;
                    updateExamPickerState();
                    return;
                }

                const template = document.getElementById('template_' + examType);

                if (!template) {
                    return;
                }

                examCardSequence += 1;
                const fragment = template.content.cloneNode(true);
                const card = fragment.querySelector('.exam-card');

                enhanceRadioArrayGroups(card, examCardSequence);
                examSectionsContainer.appendChild(fragment);
                initInterviewQuestionControls(card);
                enhanceLabels(card);
                setDefaultRequiredFields(card);
                initCharacterCounters(card);
                isChoosingExam = false;
                updateExamPickerState();
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            function initInterviewQuestionControls(card) {
                const container = card.querySelector('.interview-extra-questions');
                const controls = card.querySelector('.interview-question-controls');
                const addButton = card.querySelector('.add-interview-question');

                if (!container || !controls || !addButton) {
                    return;
                }

                addButton.addEventListener('click', function () {
                    addInterviewQuestion(container);
                    controls.style.display = container.children.length >= 3 ? 'none' : 'flex';
                    updateExamPickerState();
                });
            }

            function addInterviewQuestion(container) {
                const questionNumber = container.children.length + 1;

                if (questionNumber > 3) {
                    return;
                }

                const pair = document.createElement('div');
                pair.className = 'qa-pair interview-extra-question';
                pair.dataset.questionNumber = String(questionNumber);
                pair.innerHTML = '' +
                    '<div>' +
                    '<label>質問' + questionNumber + '</label>' +
                    '<input type="text" name="質問' + questionNumber + '[]" maxlength="30" placeholder="質問された項目" required>' +
                    '</div>' +
                    '<div>' +
                    '<label class="soft-label">答え' + questionNumber + '</label>' +
                    '<textarea name="答え' + questionNumber + '[]" maxlength="85" placeholder="返答を記入" required></textarea>' +
                    '<button type="button" class="remove-interview-question">削除</button>' +
                    '</div>';

                container.appendChild(pair);
                initCharacterCounters(pair);

                pair.querySelector('.remove-interview-question').addEventListener('click', function () {
                    pair.remove();
                    renumberInterviewQuestions(container);

                    const controls = container.parentElement.querySelector('.interview-question-controls');
                    if (controls) {
                        controls.style.display = 'flex';
                    }

                    updateExamPickerState();
                });
            }

            function renumberInterviewQuestions(container) {
                container.querySelectorAll('.interview-extra-question').forEach(function (pair, index) {
                    const questionNumber = index + 1;
                    const questionLabel = pair.querySelector('label');
                    const questionInput = pair.querySelector('input');
                    const answerLabel = pair.querySelector('.soft-label');
                    const answerTextarea = pair.querySelector('textarea');

                    pair.dataset.questionNumber = String(questionNumber);
                    questionLabel.textContent = '質問' + questionNumber;
                    questionInput.name = '質問' + questionNumber + '[]';
                    answerLabel.textContent = '答え' + questionNumber;
                    answerTextarea.name = '答え' + questionNumber + '[]';
                });
            }

            function buildConfirmOutput() {
                const formData = getCleanFormData();
                const grouped = {};

                for (const [key, value] of formData.entries()) {
                    const cleanKey = cleanDisplayText(key.replace(/\[\]$/, ''));

                    if (!value) {
                        continue;
                    }

                    if (!grouped[cleanKey]) {
                        grouped[cleanKey] = [];
                    }

                    grouped[cleanKey].push(value);
                }

                let html = '<table class="confirm-table"><tbody>';

                Object.keys(grouped).forEach(function (key) {
                    html += '<tr>';
                    html += '<th>' + escapeHtml(key) + '</th>';
                    html += '<td>' + escapeHtml(grouped[key].join('\n')) + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                confirmOutput.innerHTML = html;
            }

            function getCleanFormData() {
                const formData = new FormData(form);

                Array.from(formData.keys()).forEach(function (key) {
                    if (key.indexOf('__radio_') === 0) {
                        formData.delete(key);
                    }
                });

                return formData;
            }

            function cleanDisplayText(value) {
                return String(value)
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            function enhanceLabels(scope) {
                scope.querySelectorAll('label').forEach(function (label) {
                    if (label.querySelector('input, select, textarea') || label.dataset.enhanced === 'true') {
                        return;
                    }

                    const originalText = label.textContent.trim();
                    const tips = originalText.match(/（[^）]+）|\([^)]*\)|※.+$/g) || [];
                    const cleanedText = cleanDisplayText(originalText)
                        .replace(/（[^）]+）|\([^)]*\)|※.+$/g, '')
                        .replace(/\s+/g, ' ')
                        .trim();

                    if (cleanedText !== originalText || tips.length) {
                        label.textContent = cleanedText;
                        tips.forEach(function (tip) {
                            const tipText = tip.replace(/^（|）$/g, '').replace(/^\(|\)$/g, '').replace(/^※\s*/, '').trim();
                            const tipElement = document.createElement('span');

                            tipElement.className = 'help-tip';
                            tipElement.tabIndex = 0;
                            tipElement.setAttribute('role', 'img');
                            tipElement.setAttribute('aria-label', tipText);
                            tipElement.dataset.tip = tipText;
                            tipElement.textContent = '?';
                            label.appendChild(tipElement);
                        });
                        label.dataset.enhanced = 'true';
                    }
                });
            }

            function setDefaultRequiredFields(scope) {
                scope.querySelectorAll('input, select, textarea').forEach(function (field) {
                    if (field.type === 'hidden' || field.disabled || field.dataset.optional === 'true') {
                        return;
                    }

                    field.required = true;
                });
            }

            function initCharacterCounters(scope) {
                scope.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(function (field) {
                    if (field.dataset.counterReady === 'true') {
                        return;
                    }

                    const maxLength = Number(field.getAttribute('maxlength'));

                    if (!maxLength) {
                        return;
                    }

                    const counter = document.createElement('div');

                    counter.className = 'char-counter';
                    counter.setAttribute('aria-live', 'polite');
                    field.insertAdjacentElement('afterend', counter);
                    field.dataset.counterReady = 'true';

                    field.addEventListener('input', function () {
                        updateCharacterCounter(field, counter, maxLength);
                    });
                    updateCharacterCounter(field, counter, maxLength);
                });
            }

            function updateCharacterCounter(field, counter, maxLength) {
                const currentLength = field.value.length;

                counter.textContent = currentLength + ' / ' + maxLength;
                counter.classList.toggle('is-limit', currentLength >= maxLength);
            }

            function updateAllCharacterCounters() {
                form.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(function (field) {
                    const counter = field.nextElementSibling;

                    if (counter && counter.classList.contains('char-counter')) {
                        updateCharacterCounter(field, counter, Number(field.getAttribute('maxlength')));
                    }
                });
            }

            function enhanceRadioArrayGroups(scope, sequenceNumber) {
                scope.querySelectorAll('[data-radio-array-name]').forEach(function (group, groupIndex) {
                    const originalName = group.dataset.radioArrayName;
                    const radioName = '__radio_' + sequenceNumber + '_' + groupIndex;
                    const hiddenInput = document.createElement('input');

                    hiddenInput.type = 'hidden';
                    hiddenInput.name = originalName;
                    group.prepend(hiddenInput);

                    group.querySelectorAll('input[type="radio"]').forEach(function (radio) {
                        radio.name = radioName;
                        radio.addEventListener('change', function () {
                            hiddenInput.value = radio.value;
                        });
                    });
                });
            }

            function updateExamPickerState() {
                const cards = examSectionsContainer.querySelectorAll('.exam-card');

                if (!cards.length) {
                    isChoosingExam = true;
                }

                form.classList.toggle('has-exam-cards', Boolean(cards.length));
                form.classList.toggle('is-choosing-exam', isChoosingExam);

                examTypeButtons.forEach(function (button) {
                    const card = findExamCard(button.dataset.examType);
                    const isSelected = Boolean(card);
                    const isComplete = isSelected && isExamCardComplete(card);

                    button.classList.toggle('is-selected', isSelected);
                    button.classList.toggle('is-complete', isComplete);
                    button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                });
            }

            function findExamCard(examType) {
                return examSectionsContainer.querySelector('.exam-card[data-exam-card="' + examType + '"]');
            }

            function isExamCardComplete(card) {
                const fields = card.querySelectorAll('input[required], select[required], textarea[required]');

                for (const field of fields) {
                    if (field.type === 'hidden' && !field.value) {
                        return false;
                    }

                    if (field.type !== 'hidden' && !field.checkValidity()) {
                        return false;
                    }
                }

                return Boolean(fields.length);
            }

            function resetAppForm() {
                form.reset();
                examSectionsContainer.innerHTML = '';
                confirmOutput.innerHTML = '';
                updateExamPickerState();
                updateApplyMethod();
                updateHiroconFields();
                initCharacterCounters(form);
                updateAllCharacterCounters();
                showStep(1);
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            document.querySelectorAll('[data-next-step]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const currentStep = button.closest('.form-step').dataset.step;

                    if (!validateCurrentStep(currentStep)) {
                        return;
                    }

                    showStep(button.dataset.nextStep);
                });
            });

            document.querySelectorAll('[data-prev-step]').forEach(function (button) {
                button.addEventListener('click', function () {
                    showStep(button.dataset.prevStep);
                });
            });

            schoolName.addEventListener('change', updateHiroconFields);
            studentNumber.addEventListener('input', function () {
                keepDigitsOnly(studentNumber, 7);
            });
            studentNumber.addEventListener('paste', function () {
                window.setTimeout(function () {
                    keepDigitsOnly(studentNumber, 7);
                }, 0);
            });
            applyMethod.addEventListener('change', updateApplyMethod);
            examTypeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    addExamSection(button.dataset.examType);
                });
            });
            addMoreExamInputButton.addEventListener('click', function () {
                isChoosingExam = true;
                updateExamPickerState();
                document.getElementById('exam_picker_panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            finishExamInputButton.addEventListener('click', function () {
                if (!examSectionsContainer.querySelector('.exam-card')) {
                    return;
                }

                if (!validateCurrentStep(3)) {
                    return;
                }

                showStep(4);
            });
            goConfirmStepButton.addEventListener('click', function () {
                if (!validateCurrentStep(4)) {
                    return;
                }

                buildConfirmOutput();
                showStep(5);
            });

            function setSubmitState(state, message) {
                submitOverlay.classList.remove('is-success', 'is-error');
                submitPanel.classList.remove('success', 'error');

                if (state) {
                    submitOverlay.classList.add('is-' + state);
                    submitPanel.classList.add(state);
                }

                submitStatus.innerHTML = message;
            }

            function unlockSubmitAfterError() {
                window.setTimeout(function () {
                    form.classList.remove('is-submitting');
                }, 2600);
            }

            form.addEventListener('submit', async function (event) {
                if (!form.checkValidity()) {
                    return;
                }

                event.preventDefault();
                form.classList.add('is-submitting');
                setSubmitState(null, 'フォームを送信しています。<br>少々お待ちください。');
                form.querySelectorAll('button').forEach(function (button) {
                    button.disabled = true;
                });

                const formData = getCleanFormData();
                formData.append('exam_report_submit', '1');

                try {
                    const response = await fetch(form.action || window.location.href, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    });
                    const responseText = await response.text();
                    const responseDocument = new DOMParser().parseFromString(responseText, 'text/html');
                    const serverMessage = responseDocument.querySelector('.exam-submit-message');
                    const isSuccess = serverMessage && serverMessage.classList.contains('success');

                    if (isSuccess) {
                        setSubmitState('success', '送信が完了しました。');
                        resetAppForm();
                        window.setTimeout(function () {
                            form.classList.remove('is-submitting');
                            submitOverlay.classList.remove('is-success', 'is-error');
                            submitPanel.classList.remove('success', 'error');
                            form.querySelectorAll('button').forEach(function (button) {
                                button.disabled = false;
                            });
                        }, 1700);
                    } else {
                        setSubmitState('error', serverMessage ? escapeHtml(serverMessage.textContent) : '送信に失敗しました。時間を置いてもう一度お試しください。');
                        form.querySelectorAll('button').forEach(function (button) {
                            button.disabled = false;
                        });
                        unlockSubmitAfterError();
                    }
                } catch (error) {
                    setSubmitState('error', '送信に失敗しました。通信状況を確認してもう一度お試しください。');
                    form.querySelectorAll('button').forEach(function (button) {
                        button.disabled = false;
                    });
                    unlockSubmitAfterError();
                }
            });

            examSectionsContainer.addEventListener('click', function (event) {
                if (event.target.classList.contains('remove-exam-card')) {
                    event.target.closest('.exam-card').remove();
                    updateExamPickerState();
                }
            });
            examSectionsContainer.addEventListener('input', function () {
                updateExamPickerState();
            });
            examSectionsContainer.addEventListener('change', function () {
                updateExamPickerState();
            });

            updateApplyMethod();
            updateHiroconFields();
            enhanceLabels(form);
            setDefaultRequiredFields(form);
            initCharacterCounters(form);
            updateApplyMethod();
            updateHiroconFields();
            updateExamPickerState();
            updateProgress();
        });
        </script>

        <?php
        return ob_get_clean();
    });

    function exam_report_sanitize_post_data($data) {
        $clean = [];

        foreach ($data as $key => $value) {
            $clean_key = sanitize_text_field($key);

            if (is_array($value)) {
                $clean[$clean_key] = array_map(function ($item) {
                    return sanitize_textarea_field($item);
                }, $value);
            } else {
                $clean[$clean_key] = $clean_key === '学籍番号'
                    ? mb_substr(preg_replace('/\D/', '', $value), 0, 7)
                    : sanitize_textarea_field($value);
            }
        }

        return $clean;
    }
