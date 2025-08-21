document.addEventListener("DOMContentLoaded", function () {
  const preview = document.getElementById("calendarPreview");
  const popover = document.getElementById("calendarPopover");
  const input = document.getElementById("execution_date");
  const monthLabel = document.getElementById("calendarMonth");
  const grid = document.getElementById("calendarGrid");
  const weekdaysHeader = document.getElementById("calendarWeekdays");
  const prevBtn = document.getElementById("prevMonth");
  const nextBtn = document.getElementById("nextMonth");
  const recurrenceSelect = document.getElementById("recurrence");
  const recurrenceMessage = document.getElementById("recurrence-message");

  let currentDate = new Date();

  const formatDateAU = (date) =>
    date.toLocaleDateString("en-AU", {
      weekday: "long",
      year: "numeric",
      month: "short",
      day: "numeric",
    });

  const formatDateISO = (date) => date.toISOString().split("T")[0];

  const getWeekOfMonth = (date) => {
    const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
    const dayOfWeek = firstDay.getDay() || 7;
    const offsetDate = date.getDate() + dayOfWeek - 1;
    return Math.ceil(offsetDate / 7);
  };

  const updateCalendar = (date = new Date()) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    const today = new Date();
    const selected = input.value;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    monthLabel.textContent = firstDay.toLocaleString("en-AU", {
      month: "long",
      year: "numeric",
    });

    const weekdays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    weekdaysHeader.innerHTML = weekdays
      .map((day) => `<div class="weekday">${day}</div>`)
      .join("");

    grid.innerHTML = "";

    const offset = (firstDay.getDay() + 6) % 7;
    for (let i = 0; i < offset; i++) {
      grid.innerHTML += `<div class="calendar-day disabled"></div>`;
    }

    const now = new Date();
    const limit48h = new Date(now.getTime() + 48 * 60 * 60 * 1000);

    for (let day = 1; day <= lastDay.getDate(); day++) {
      const cellDate = new Date(year, month, day, 12);
      const iso = formatDateISO(cellDate);

      const isTooSoon = cellDate < limit48h;
      const selectedClass = selected === iso ? "selected" : "";
      const todayClass = formatDateISO(today) === iso ? "today" : "";
      const disabledClass = isTooSoon ? "disabled" : "";

      const classList = ["calendar-day", selectedClass, todayClass, disabledClass]
        .filter(Boolean)
        .join(" ");

      grid.innerHTML += `<div class="${classList}" data-value="${iso}">${day}</div>`;
    }

    document.querySelectorAll(".calendar-day").forEach((el) => {
      if (!el.classList.contains("disabled")) {
        el.addEventListener("click", () => {
          const selected = el.dataset.value;
          const [yearStr, monthStr, dayStr] = selected.split('-');
          const date = new Date(parseInt(yearStr), parseInt(monthStr) - 1, parseInt(dayStr), 12);

          const recurrence = recurrenceSelect.value;
          const weekNum = getWeekOfMonth(date);
          const dayName = date.toLocaleDateString("en-AU", { weekday: "long" });

          input.value = selected;
          preview.textContent = formatDateAU(date);

          const oldOk = document.getElementById("closeCalendarBtn");
          if (oldOk) oldOk.remove();

          if (["weekly", "fortnightly", "monthly"].includes(recurrence)) {
            recurrenceMessage.style.display = "block";

            if (recurrence === "weekly") {
              recurrenceMessage.textContent = `Os serviços semanais acontecerão todas as ${dayName}s até que seja solicitada alteração.`;
            } else if (recurrence === "fortnightly") {
              recurrenceMessage.textContent = `Os serviços quinzenais acontecerão todas as ${dayName}s a cada 15 dias até que seja solicitada alteração.`;
            } else if (recurrence === "monthly") {
              recurrenceMessage.textContent = `O serviço mensal acontecerá todas as ${dayName}s da ${weekNum}ª semana do mês até que seja solicitada alteração.`;
            }

const okBtn = document.createElement("button");
okBtn.id = "closeCalendarBtn";
okBtn.textContent = "Ok";
okBtn.className = "btn btn-primary";

const wrapper = document.createElement("div");
wrapper.style.marginTop = "8px"; // Coloca o botão em uma linha abaixo
wrapper.appendChild(okBtn);
recurrenceMessage.appendChild(wrapper);

okBtn.addEventListener("click", () => {
  popover.classList.remove("show");
  preview.setAttribute("aria-expanded", "false");
  wrapper.remove(); // Remove o botão e a margem juntos
});

          } else {
            recurrenceMessage.style.display = "none";
            popover.classList.remove("show");
            preview.setAttribute("aria-expanded", "false");
          }
        });
      }
    });
  };

  preview.addEventListener("click", () => {
    popover.classList.remove("hidden");
    popover.classList.toggle("show");
    const isOpen = popover.classList.contains("show");
    preview.setAttribute("aria-expanded", isOpen.toString());
    if (isOpen) updateCalendar(currentDate);
  });

  document.addEventListener("click", (e) => {
    if (!popover.contains(e.target) && !preview.contains(e.target)) {
      popover.classList.remove("show");
      preview.setAttribute("aria-expanded", "false");
    }
  });

  prevBtn.addEventListener("click", () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    updateCalendar(currentDate);
  });

  nextBtn.addEventListener("click", () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    updateCalendar(currentDate);
  });

  recurrenceSelect.addEventListener("change", () => {
    const val = recurrenceSelect.value;
    if (["weekly", "fortnightly", "monthly"].includes(val)) {
      recurrenceMessage.style.display = "block";
      recurrenceMessage.textContent = "Escolha a data para iniciar o serviço.";
    } else {
      recurrenceMessage.style.display = "none";
    }
  });

  recurrenceSelect.dispatchEvent(new Event("change"));
});