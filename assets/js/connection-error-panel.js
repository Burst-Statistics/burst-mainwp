(function () {
	"use strict";

	var l10n = window.burstMainwpConnectionErrorL10n || {};
	var copiedMsg =
		typeof l10n.copied === "string" ? l10n.copied : "Report copied.";
	var fallbackMsg =
		typeof l10n.fallback === "string" ? l10n.fallback : "Copy failed.";

	var panels = document.querySelectorAll(
		'[data-burst-mainwp-connection-panel="1"]',
	);
	if (!panels.length) {
		return;
	}

	panels.forEach(function (panel) {
		var buttonId = panel.getAttribute("data-copy-button-id");
		var reportId = panel.getAttribute("data-report-id");
		var statusId = panel.getAttribute("data-status-id");

		if (!buttonId || !reportId || !statusId) {
			return;
		}

		var button = document.getElementById(buttonId);
		var report = document.getElementById(reportId);
		var status = document.getElementById(statusId);

		if (!button || !report || !status) {
			return;
		}

		if (button.getAttribute("data-burst-mainwp-bound") === "1") {
			return;
		}

		button.setAttribute("data-burst-mainwp-bound", "1");

		button.addEventListener("click", function () {
			report.focus();
			report.select();

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard
					.writeText(report.value)
					.then(function () {
						status.textContent = copiedMsg;
					})
					.catch(function () {
						status.textContent = fallbackMsg;
					});
				return;
			}

			try {
				document.execCommand("copy");
				status.textContent = copiedMsg;
			} catch (e) {
				status.textContent = fallbackMsg;
			}
		});
	});
})();
