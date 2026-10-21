// ===============================
// ATALHOS
// ===============================

const $ = (selector, element = document) => element.querySelector(selector);
const $$ = (selector, element = document) => [...element.querySelectorAll(selector)];


// ===============================
// CONFIGURAÇÃO DO WHATSAPP
// ===============================

// TROQUE pelo número real da Larissa: 55 + DDD + número, sem espaços e sem símbolos.
const WHATSAPP_NUMBER = "5511940546668";

let lastWhatsappUrl = "";


// ===============================
// HEADER AO ROLAR
// ===============================

const header = $(".site-header");

if (header) {
  const updateHeader = () => {
    header.classList.toggle("scrolled", window.scrollY > 24);
  };

  updateHeader();
  window.addEventListener("scroll", updateHeader, { passive: true });
}


// ===============================
// ELEMENTOS DO AGENDAMENTO
// ===============================

const service = $("#service");
const dateInput = $("#date");
const time = $("#time");
const clientName = $("#clientName");
const clientPhone = $("#clientPhone");
const whatsappConsent = $("#whatsappConsent");

const continueBtn = $("#continueBtn");
const clientContinueBtn = $("#clientContinueBtn");
const backToScheduleBtn = $("#backToScheduleBtn");
const confirmBtn = $("#confirmBtn");
const backBtn = $("#backBtn");
const newBookingBtn = $("#newBookingBtn");
const openWhatsappBtn = $("#openWhatsappBtn");
const whatsappContactBtn = $("#whatsappContactBtn");

const stepIndicator = $(".step-indicator");
const toast = $(".toast");
const TOTAL_STEPS = 4;


// ===============================
// DATA MÍNIMA
// ===============================

if (dateInput) {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);

  const year = tomorrow.getFullYear();
  const month = String(tomorrow.getMonth() + 1).padStart(2, "0");
  const day = String(tomorrow.getDate()).padStart(2, "0");

  dateInput.min = `${year}-${month}-${day}`;
}


// ===============================
// TOAST
// ===============================

let toastTimer;

function showToast(message) {
  if (!toast) return;

  toast.textContent = message;
  toast.classList.add("show");

  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 2200);
}


// ===============================
// ETAPAS DO AGENDAMENTO
// ===============================

function setStep(stepNumber) {
  const bookingSteps = $$(".booking-step");

  bookingSteps.forEach((step) => {
    const currentStep = Number(step.dataset.step);
    step.classList.toggle("active", currentStep === stepNumber);
  });

  if (stepIndicator) {
    stepIndicator.textContent = `${stepNumber}/${TOTAL_STEPS}`;
  }
}


// ===============================
// DADOS DO SERVIÇO
// ===============================

function parseService() {
  if (!service || !service.value) return null;

  const [name, rawPrice, rawDuration] = service.value.split("|");

  return {
    name,
    price: Number(rawPrice),
    duration: Number(rawDuration),
  };
}

function formatDate(date) {
  if (!date) return "";

  const formattedDate = new Date(`${date}T12:00:00`);

  return new Intl.DateTimeFormat("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(formattedDate);
}

function formatPrice(price) {
  return price.toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
}

function onlyDigits(value) {
  return String(value || "").replace(/\D/g, "");
}

function formatPhone(value) {
  const digits = onlyDigits(value).slice(0, 11);

  if (digits.length <= 2) return digits;
  if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;

  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function validateClientData() {
  const name = clientName?.value.trim() || "";
  const phoneDigits = onlyDigits(clientPhone?.value);

  if (name.length < 3) {
    showToast("Informe seu nome.");
    clientName?.focus();
    return false;
  }

  if (phoneDigits.length < 10 || phoneDigits.length > 11) {
    showToast("Informe um WhatsApp válido com DDD.");
    clientPhone?.focus();
    return false;
  }

  if (whatsappConsent && !whatsappConsent.checked) {
    showToast("Autorize o contato pelo WhatsApp para continuar.");
    return false;
  }

  return true;
}

function fillReview() {
  const selectedService = parseService();

  if (!selectedService) return;

  const reviewClient = $("#reviewClient");
  const reviewPhone = $("#reviewPhone");
  const reviewService = $("#reviewService");
  const reviewDate = $("#reviewDate");
  const reviewTime = $("#reviewTime");
  const reviewPrice = $("#reviewPrice");

  if (reviewClient) reviewClient.textContent = clientName?.value.trim() || "—";
  if (reviewPhone) reviewPhone.textContent = clientPhone?.value.trim() || "—";
  if (reviewService) reviewService.textContent = selectedService.name;
  if (reviewDate) reviewDate.textContent = formatDate(dateInput.value);
  if (reviewTime) reviewTime.textContent = time.value;
  if (reviewPrice) reviewPrice.textContent = formatPrice(selectedService.price);
}


// ===============================
// WHATSAPP
// ===============================

function buildWhatsappUrl() {
  const selectedService = parseService();

  if (!selectedService || !dateInput?.value || !time?.value || !clientName?.value || !clientPhone?.value) {
    return "";
  }

  const message = `Olá, Larissa! Gostaria de solicitar um agendamento.\n\nCliente: ${clientName.value.trim()}\nWhatsApp: ${clientPhone.value.trim()}\nServiço: ${selectedService.name}\nData: ${formatDate(dateInput.value)}\nHorário: ${time.value}\nValor: ${formatPrice(selectedService.price)}\n\nPode confirmar a disponibilidade desse horário para mim?`;

  return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
}

function openWhatsapp(url) {
  if (!url) return;
  window.open(url, "_blank", "noopener,noreferrer");
}


// ===============================
// MÁSCARA DO WHATSAPP DA CLIENTE
// ===============================

if (clientPhone) {
  clientPhone.addEventListener("input", () => {
    clientPhone.value = formatPhone(clientPhone.value);
  });
}


// ===============================
// BOTÃO CONTINUAR
// ===============================

if (continueBtn) {
  continueBtn.addEventListener("click", () => {
    const selectedService = parseService();

    if (!selectedService || !dateInput?.value || !time?.value) {
      showToast("Escolha o serviço, a data e o horário.");
      return;
    }

    setStep(2);
  });
}

if (clientContinueBtn) {
  clientContinueBtn.addEventListener("click", () => {
    if (!validateClientData()) return;
    fillReview();
    setStep(3);
  });
}


// ===============================
// VOLTAR
// ===============================

if (backToScheduleBtn) {
  backToScheduleBtn.addEventListener("click", () => setStep(1));
}

if (backBtn) {
  backBtn.addEventListener("click", () => setStep(2));
}


// ===============================
// CONFIRMAR / PREPARAR WHATSAPP
// ===============================

if (confirmBtn) {
  confirmBtn.addEventListener("click", () => {
    lastWhatsappUrl = buildWhatsappUrl();

    if (!lastWhatsappUrl) {
      showToast("Preencha os dados do agendamento.");
      setStep(1);
      return;
    }

    setStep(4);
    showToast("Solicitação pronta para o WhatsApp.");

    setTimeout(() => openWhatsapp(lastWhatsappUrl), 450);
  });
}

if (openWhatsappBtn) {
  openWhatsappBtn.addEventListener("click", () => {
    if (!lastWhatsappUrl) lastWhatsappUrl = buildWhatsappUrl();

    if (!lastWhatsappUrl) {
      showToast("Faça um agendamento primeiro.");
      setStep(1);
      return;
    }

    openWhatsapp(lastWhatsappUrl);
  });
}

if (whatsappContactBtn) {
  whatsappContactBtn.addEventListener("click", () => {
    const message = "Olá, Larissa! Vim pelo site e gostaria de tirar uma dúvida sobre os serviços.";
    const url = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
    openWhatsapp(url);
  });
}


// ===============================
// NOVO AGENDAMENTO
// ===============================

if (newBookingBtn) {
  newBookingBtn.addEventListener("click", () => {
    if (service) service.value = "";
    if (dateInput) dateInput.value = "";
    if (time) time.value = "";
    if (clientName) clientName.value = "";
    if (clientPhone) clientPhone.value = "";
    if (whatsappConsent) whatsappConsent.checked = false;

    lastWhatsappUrl = "";
    setStep(1);
  });
}


// ===============================
// CARDS DOS SERVIÇOS
// ===============================

const serviceCards = $$(".service-card");

serviceCards.forEach((card) => {
  const button = card.querySelector("button");
  if (!button) return;

  button.addEventListener("click", () => {
    if (service && card.dataset.service) {
      service.value = card.dataset.service;
    }

    const bookingSection = $("#agendamento");

    if (bookingSection) {
      bookingSection.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
    }

    setStep(1);

    setTimeout(() => dateInput?.focus(), 700);
  });
});


// ===============================
// VÍDEO DO SOBRE CONTROLADO PELO SCROLL
// ===============================

const aboutSection = $("#sobre");
const aboutVideo = $("#aboutScrollVideo");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

if (aboutSection && aboutVideo && !reduceMotion) {
  let duration = 0;
  let targetTime = 0;
  let smoothTime = 0;
  let rafId = null;

  const getProgress = () => {
    const rect = aboutSection.getBoundingClientRect();
    const scrollableDistance = Math.max(1, aboutSection.offsetHeight - window.innerHeight);
    const travelled = Math.min(Math.max(-rect.top, 0), scrollableDistance);
    return travelled / scrollableDistance;
  };

  const updateTargetTime = () => {
    if (!duration) return;
    const progress = getProgress();
    targetTime = progress * Math.max(0, duration - 0.05);
  };

  const animateVideo = () => {
    if (!duration) {
      rafId = requestAnimationFrame(animateVideo);
      return;
    }

    updateTargetTime();
    smoothTime += (targetTime - smoothTime) * 0.09;

    if (Number.isFinite(smoothTime) && Math.abs(aboutVideo.currentTime - smoothTime) > 0.01) {
      try {
        aboutVideo.currentTime = smoothTime;
      } catch (error) {
        // Alguns navegadores podem bloquear seeks muito rápidos enquanto o vídeo carrega.
      }
    }

    rafId = requestAnimationFrame(animateVideo);
  };

  const prepareVideo = () => {
    duration = aboutVideo.duration || 0;
    aboutVideo.pause();
    updateTargetTime();
    smoothTime = targetTime;
    if (!rafId) animateVideo();
  };

  aboutVideo.addEventListener("loadedmetadata", prepareVideo);
  window.addEventListener("resize", updateTargetTime);
  window.addEventListener("scroll", updateTargetTime, { passive: true });

  if (aboutVideo.readyState >= 1) {
    prepareVideo();
  }
}


// ===============================
// ANIMAÇÕES AO ROLAR
// ===============================

const revealElements = $$(".reveal");

if (revealElements.length > 0 && "IntersectionObserver" in window) {
  const revealObserver = new IntersectionObserver(
    (entries, observer) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12 }
  );

  revealElements.forEach((element) => revealObserver.observe(element));
}


// ===============================
// MENU ATIVO CONFORME A SEÇÃO
// ===============================

const sections = $$("main section[id]");
const navLinks = $$(".desktop-nav a");

if (sections.length > 0 && navLinks.length > 0 && "IntersectionObserver" in window) {
  const sectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        navLinks.forEach((link) => {
          const isActive = link.getAttribute("href") === `#${entry.target.id}`;
          link.classList.toggle("active", isActive);
        });
      });
    },
    { rootMargin: "-45% 0px -50% 0px" }
  );

  sections.forEach((section) => sectionObserver.observe(section));
}


// ===============================
// LINKS COM ROLAGEM SUAVE
// ===============================

$$("a[href^='#']").forEach((link) => {
  link.addEventListener("click", (event) => {
    const targetId = link.getAttribute("href");
    if (!targetId || targetId === "#") return;

    const target = $(targetId);
    if (!target) return;

    event.preventDefault();
    target.scrollIntoView({ behavior: "smooth", block: "start" });
  });
});


// ===============================
// INICIAR SISTEMA
// ===============================

setStep(1);
console.log("Larissa Ferreira Beauty carregado.");
