// Agent-only registration. Existing endpoint remains pages/agentReg.
const settings = window.agencyRegistration;
const words = settings.text;
const form = document.getElementById("agentRegister");
let version = settings.version;
let step = 0;
let busy = false;
let dirty = false;
const groups = [
  "legal",
  "branches",
  "contacts",
  "links",
  "documents",
  "schools",
];
const specifications = {
  legal: [
    ["fullName", "text", true],
    ["jobTitle", "text", false],
    ["personEmail", "email", true],
    ["phone", "tel", true],
    ["primary", "radio", false],
  ],
  branches: [
    ["branchName", "text", true],
    ["branchCountry", "select", true],
    ["address", "textarea", true],
    ["manager", "text", false],
    ["personEmail", "email", false],
    ["active", "checkbox", false],
  ],
  contacts: [
    ["fullName", "text", true],
    ["department", "text", false],
    ["personEmail", "email", true],
    ["phone", "tel", true],
    ["primary", "radio", false],
  ],
  links: [
    ["linkType", "select", true],
    ["url", "url", true],
    ["displayLabel", "text", false],
  ],
  documents: [
    ["documentType", "select", true],
    ["file", "file", true],
    ["issueDate", "date", false],
    ["expiryDate", "date", false],
  ],
  schools: [
    ["school_id", "select", true],
    ["requestNote", "textarea", false],
    ["agreement", "file", false],
  ],
};
let sequence = 0;
function countryLabel(country) {
  if (
    settings.lang === "ja" &&
    country.code &&
    typeof Intl.DisplayNames === "function"
  ) {
    try {
      return new Intl.DisplayNames(["ja"], { type: "region" }).of(country.code);
    } catch (error) {}
  }
  return country.value;
}
const countrySelect = form.elements.namedItem("country");
for (const option of countrySelect.options) {
  const country = settings.countries.find(
    (item) => item.value === option.value,
  );
  if (country) option.textContent = countryLabel(country);
}

function addRow(group, values = {}) {
  if (document.querySelectorAll("#rows-" + group + " .repeat-row").length >= 20)
    return;
  const row = document.createElement("div");
  row.className = "repeat-row";
  row.dataset.group = group;
  row.dataset.existingId = values.existing_id || 0;
  const grid = document.createElement("div");
  grid.className = "field-grid";
  const rowNumber = ++sequence;
  for (const [key, type, required] of specifications[group]) {
    const wrapper = document.createElement("div");
    wrapper.className = "input-group";
    const label = document.createElement("label");
    label.htmlFor = "row-" + rowNumber + "-" + key;
    label.textContent =
      (words["agy_" + (key === "school_id" ? "targetSchool" : key)] || key) +
      (required ? " *" : "");
    let input;
    if (type === "select") {
      input = document.createElement("select");
      input.add(new Option(words.agy_select, ""));
      if (key === "branchCountry") {
        for (const country of settings.countries)
          input.add(new Option(countryLabel(country), country.value));
      } else if (key === "school_id") {
        for (const school of settings.schools)
          input.add(
            new Option(
              school["name_" + settings.lang] || school.name_eng,
              school.id,
            ),
          );
      } else {
        const options =
          key === "linkType"
            ? ["website", "facebook", "instagram", "linkedin", "other"]
            : [
                "company_registration",
                "business_license",
                "company_profile",
                "other",
              ];
        for (const value of options)
          input.add(new Option(words["agy_" + value], value));
      }
    } else if (type === "textarea") {
      input = document.createElement("textarea");
      input.rows = 3;
      input.maxLength = 2000;
    } else {
      input = document.createElement("input");
      input.type = type;
      if (["text", "email", "tel", "url"].includes(type)) input.maxLength = 300;
      if (type === "file")
        input.accept = group === "schools" ? ".pdf" : ".pdf,.jpg,.jpeg,.png";
    }
    input.id = label.htmlFor;
    input.dataset.key = key;
    if (type === "radio" || type === "checkbox") {
      input.name = group + "-" + key;
      input.checked =
        values[key] !== undefined
          ? Boolean(Number(values[key]))
          : document.querySelectorAll("#rows-" + group + " .repeat-row")
              .length === 0;
      label.className = "checkbox-label";
      label.appendChild(input);
      wrapper.appendChild(label);
    } else {
      if (type !== "file") input.value = values[key] ?? "";
      input.required =
        required && !(type === "file" && Number(values.existing_id));
      wrapper.append(label, input);
    }
    grid.appendChild(wrapper);
  }
  if (group === "schools") {
    const status = document.createElement("p");
    status.className = "pending-label";
    status.textContent = words.agy_partnershipStatus + ": " + words.agy_pending;
    grid.appendChild(status);
  }
  const saved = settings.uploads.find(
    (file) => Number(file.id) === Number(values.existing_id),
  );
  if (saved) {
    const note = document.createElement("p");
    note.className = "saved-file";
    note.textContent = words.agy_fileSaved + ": " + saved.original_name;
    grid.appendChild(note);
  }
  const remove = document.createElement("button");
  remove.type = "button";
  remove.className = "remove-row";
  remove.textContent = words.agy_remove;
  remove.addEventListener("click", () => {
    row.remove();
    dirty = true;
  });
  row.append(grid, remove);
  document.getElementById("rows-" + group).appendChild(row);
}
for (const group of groups) {
  const rows = settings.data[group];
  if (Array.isArray(rows) && rows.length)
    rows.forEach((row) => addRow(group, row));
  else if (["legal", "contacts", "schools"].includes(group)) addRow(group);
}
for (const [key, value] of Object.entries(settings.data)) {
  const input = form.elements.namedItem(key);
  if (input && typeof value === "string") input.value = value;
}
document.querySelectorAll("[data-add]").forEach((button) =>
  button.addEventListener("click", () => {
    addRow(button.dataset.add);
    dirty = true;
  }),
);
function showStep(number) {
  step = Math.max(0, Math.min(6, number));
  document
    .querySelectorAll("[data-panel]")
    .forEach((panel) => (panel.hidden = Number(panel.dataset.panel) !== step));
  document.querySelectorAll("[data-step]").forEach((button) => {
    button.classList.toggle("active", Number(button.dataset.step) === step);
    button.setAttribute(
      "aria-current",
      Number(button.dataset.step) === step ? "step" : "false",
    );
  });
  document.getElementById("previousStep").hidden = step === 0;
  document.getElementById("nextStep").hidden = step === 6;
  document.getElementById("submitRequest").hidden = step !== 6;
  const percentage = Math.round(((step + 1) / 7) * 100);
  document.getElementById("progressPercent").textContent = percentage + "%";
  document.getElementById("progressBar").style.width = percentage + "%";
  document.getElementById("stepLabel").textContent =
    words.agy_step + " " + (step + 1) + " " + words.agy_of + " 7";
}
function message(text, error = false) {
  const box = document.getElementById("formMessage");
  box.textContent = text;
  box.className = error ? "message error" : "message success";
}
function payload() {
  const data = {};
  for (const key of [
    "companyName",
    "registrationNumber",
    "country",
    "headOfficeAddress",
    "email",
    "contactNumber",
    "postalCode",
    "localCompanyName",
    "city",
    "yearEstablished",
  ])
    data[key] = form.elements.namedItem(key).value;
  for (const group of groups) {
    data[group] = [];
    document
      .querySelectorAll("#rows-" + group + " .repeat-row")
      .forEach((row) => {
        const record = { existing_id: Number(row.dataset.existingId) };
        row.querySelectorAll("[data-key]").forEach((input) => {
          if (input.type !== "file")
            record[input.dataset.key] = ["checkbox", "radio"].includes(
              input.type,
            )
              ? input.checked
                ? 1
                : 0
              : input.value;
        });
        data[group].push(record);
      });
  }
  return data;
}
async function save(action) {
  if (busy) return false;
  busy = true;
  document
    .querySelectorAll(".button,[data-step],.remove-row")
    .forEach((button) => (button.disabled = true));
  message(words.agy_busy);
  try {
    const body = new FormData();
    body.append("action", action);
    body.append("payload", JSON.stringify(payload()));
    body.append("version", version);
    body.append("csrf", form.elements.namedItem("csrf").value);
    if (action === "submit") {
      body.append("password", form.elements.namedItem("password").value);
      body.append(
        "confirmPassword",
        form.elements.namedItem("confirmPassword").value,
      );
      body.append(
        "declaration",
        form.elements.namedItem("declaration").checked ? "1" : "0",
      );
    }
    for (const [group, prefix] of [
      ["documents", "document_"],
      ["schools", "agreement_"],
    ])
      document
        .querySelectorAll("#rows-" + group + " .repeat-row")
        .forEach((row, index) => {
          const file = row.querySelector('input[type="file"]');
          if (file.files[0]) body.append(prefix + index, file.files[0]);
        });
    const response = await fetch("agentReg", {
      method: "POST",
      body,
      credentials: "same-origin",
    });
    const result = await response.json();
    if (result.status !== "success") {
      message(result.message || words.agy_error, true);
      return false;
    }
    version = result.version;
    for (const uploaded of result.uploads || []) {
      const row = document.querySelectorAll(
        "#rows-" + uploaded.group + " .repeat-row",
      )[uploaded.index];
      row.dataset.existingId = uploaded.id;
      const file = row.querySelector('input[type="file"]');
      file.value = "";
      file.required = false;
      let note = row.querySelector(".saved-file");
      if (!note) {
        note = document.createElement("p");
        note.className = "saved-file";
        row.querySelector(".field-grid").appendChild(note);
      }
      note.textContent = words.agy_fileSaved + ": " + uploaded.name;
    }
    dirty = false;
    message(result.message);
    if (result.submitted) {
      form.hidden = true;
      document
        .querySelectorAll("[data-step]")
        .forEach((button) => (button.hidden = true));
      form.elements.namedItem("password").value = "";
      form.elements.namedItem("confirmPassword").value = "";
    }
    return true;
  } catch (error) {
    message(words.agy_network, true);
    return false;
  } finally {
    busy = false;
    document
      .querySelectorAll(".button,[data-step],.remove-row")
      .forEach((button) => (button.disabled = false));
  }
}
document
  .getElementById("saveDraft")
  .addEventListener("click", () => save("draft"));
document.getElementById("nextStep").addEventListener("click", async () => {
  if (await save("draft")) showStep(step + 1);
});
document
  .getElementById("previousStep")
  .addEventListener("click", () => showStep(step - 1));
document.querySelectorAll("[data-step]").forEach((button) =>
  button.addEventListener("click", () => {
    if (!busy) showStep(Number(button.dataset.step));
  }),
);
form.addEventListener("input", () => (dirty = true));
form.addEventListener("submit", async (event) => {
  event.preventDefault();
  for (const input of form.querySelectorAll("input,textarea,select")) {
    if (!input.checkValidity()) {
      showStep(Number(input.closest("[data-panel]").dataset.panel));
      input.reportValidity();
      return;
    }
  }
  await save("submit");
});
window.addEventListener("beforeunload", (event) => {
  if (dirty && !busy) {
    event.preventDefault();
    event.returnValue = "";
  }
});
showStep(0);
if (version) message(words.agy_restored);
