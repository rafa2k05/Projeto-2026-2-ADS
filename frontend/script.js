// =====================================================================
// SHORTCUTS / ATALHOS
// =====================================================================

// Short helpers to select one element or many elements.
// Atalhos curtos para selecionar um elemento ou vários.
const $ = (selector, element = document) => element.querySelector(selector);
const $$ = (selector, element = document) => [...element.querySelectorAll(selector)];


// =====================================================================
// SETTINGS / CONFIGURAÇÕES
// =====================================================================

// Larissa's WhatsApp: 55 + area code + number, no spaces or symbols.
// WhatsApp da Larissa: 55 + DDD + número, sem espaços e sem símbolos.
const WHATSAPP_NUMBER = "5511940546668";

// APIs PHP. O frontend não é a fonte de verdade dos preços, horários ou disponibilidade.
const API_BASE = "../backend/api";
const MAX_ADVANCE_DAYS = 90; // apenas para limitar o seletor; o PHP valida novamente
const CLOSED_WEEKDAYS = [];

// Last WhatsApp link built (reused by the "Open WhatsApp" button).
// Último link do WhatsApp montado (reaproveitado pelo botão "Abrir WhatsApp").
let lastWhatsappUrl = "";
let csrfToken = "";
let servicesCache = [];
let lastBooking = null;


// =====================================================================
// HEADER ON SCROLL / HEADER AO ROLAR
// =====================================================================

const header = $(".site-header");

if (header) {
  // Adds the "scrolled" class after the page moves a little.
  // Adiciona a classe "scrolled" depois que a página rola um pouco.
  const updateHeader = () => {
    header.classList.toggle("scrolled", window.scrollY > 24);
  };

  updateHeader();
  // passive: true tells the browser we never block scrolling (smoother).
  // passive: true avisa o navegador que nunca bloqueamos a rolagem (mais fluido).
  window.addEventListener("scroll", updateHeader, { passive: true });
}


// =====================================================================
// MOBILE MENU / MENU MOBILE
// (NEW: the CSS already had .open, but nothing in JS toggled it)
// (NOVO: o CSS já tinha o .open, mas nada no JS ativava)
// =====================================================================

const menuToggle = $("#menuToggle");
const mobileMenu = $("#mobileMenu");

// Opens or closes the menu and keeps accessibility attributes in sync.
// Abre ou fecha o menu e mantém os atributos de acessibilidade atualizados.
function setMenu(open) {
  if (!menuToggle || !mobileMenu) return;

  menuToggle.classList.toggle("open", open);
  mobileMenu.classList.toggle("open", open);
  menuToggle.setAttribute("aria-expanded", String(open));
  menuToggle.setAttribute("aria-label", open ? "Fechar menu" : "Abrir menu");

  // Block page scrolling behind the open menu.
  // Trava a rolagem da página por trás do menu aberto.
  document.body.style.overflow = open ? "hidden" : "";
}

if (menuToggle && mobileMenu) {
  // Hamburger button: toggle.
  // Botão hambúrguer: alterna aberto/fechado.
  menuToggle.addEventListener("click", () => {
    setMenu(!mobileMenu.classList.contains("open"));
  });

  // Clicking any link inside the menu closes it.
  // Clicar em qualquer link dentro do menu fecha o menu.
  mobileMenu.addEventListener("click", (event) => {
    if (event.target.closest("a")) setMenu(false);
  });

  // Escape key closes the menu.
  // A tecla Escape fecha o menu.
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setMenu(false);
  });
}


// =====================================================================
// BOOKING ELEMENTS / ELEMENTOS DO AGENDAMENTO
// =====================================================================

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


// =====================================================================
// DATE LIMITS / LIMITES DA DATA
// =====================================================================

// Turns a Date object into "YYYY-MM-DD" using the LOCAL date (not UTC).
// Transforma um Date em "AAAA-MM-DD" usando a data LOCAL (não UTC).
function toISODate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

if (dateInput) {
  // Earliest date = tomorrow (same rule as the backend).
  // Data mínima = amanhã (mesma regra do backend).
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  dateInput.min = toISODate(tomorrow);

  // Latest date = today + MAX_ADVANCE_DAYS (NEW: the backend already had this limit).
  // Data máxima = hoje + MAX_ADVANCE_DAYS (NOVO: o backend já tinha esse limite).
  const lastDay = new Date();
  lastDay.setDate(lastDay.getDate() + MAX_ADVANCE_DAYS);
  dateInput.max = toISODate(lastDay);
}

// Returns an error message for an invalid date, or "" if it is fine.
// The min/max attributes only block the date picker; a typed date can still slip through,
// so we check it here too (NEW).
// Devolve uma mensagem de erro para data inválida, ou "" se estiver tudo certo.
// Os atributos min/max só bloqueiam o seletor de datas; uma data digitada ainda passaria,
// por isso conferimos aqui também (NOVO).
function getDateError(value) {
  if (!value) return "Escolha a data.";

  if (dateInput.min && value < dateInput.min) {
    return "Escolha uma data a partir de amanhã.";
  }

  if (dateInput.max && value > dateInput.max) {
    return `Só é possível agendar em até ${MAX_ADVANCE_DAYS} dias.`;
  }

  // T12:00:00 avoids timezone shifts that could move the date to another day.
  // T12:00:00 evita mudanças de fuso que poderiam levar a data para outro dia.
  const weekday = new Date(`${value}T12:00:00`).getDay();
  if (CLOSED_WEEKDAYS.includes(weekday)) {
    return "Não atendemos neste dia da semana.";
  }

  return "";
}


// =====================================================================
// TOAST (small message) / TOAST (mensagem rápida)
// =====================================================================

let toastTimer;

function showToast(message) {
  if (!toast) return;

  toast.textContent = message;
  toast.classList.add("show");

  // Restart the timer so messages never hide too early.
  // Reinicia o contador para a mensagem nunca sumir cedo demais.
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 2200);
}


// ===== Integração com as APIs PHP =====
async function apiRequest(path, options = {}) {
  const headers = { ...(options.headers || {}) };
  if (options.body) headers['Content-Type'] = 'application/json';
  if (options.method && options.method !== 'GET' && csrfToken) headers['X-CSRF-Token'] = csrfToken;
  const response = await fetch(`${API_BASE}/${path}`, { credentials: 'same-origin', ...options, headers });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.erro || 'Não foi possível concluir a operação.');
  return payload;
}

async function loadCsrf() {
  const data = await apiRequest('me.php');
  csrfToken = data.csrf || '';
}

async function loadServices() {
  const data = await apiRequest('listar_servicos.php');
  servicesCache = data.servicos || [];
  if (!service) return;
  service.innerHTML = '<option value="">Selecione o serviço</option>';
  servicesCache.forEach((item) => {
    const option = document.createElement('option');
    option.value = String(item.id);
    option.textContent = `${item.nome} — ${formatPrice(item.preco)}`;
    service.appendChild(option);
  });
}

async function loadAvailableTimes() {
  if (!time) return;
  const selectedService = parseService();
  const date = dateInput?.value || '';
  time.disabled = true;
  time.innerHTML = '<option value="">Carregando horários...</option>';
  if (!selectedService || !date) {
    time.innerHTML = '<option value="">Escolha primeiro o serviço e a data</option>';
    return;
  }
  try {
    const query = new URLSearchParams({ data: date, servico_id: String(selectedService.id) });
    const data = await apiRequest(`listar_horarios.php?${query.toString()}`);
    time.innerHTML = '<option value="">Escolha o horário</option>';
    (data.horarios || []).forEach((hora) => {
      const option = document.createElement('option');
      option.value = hora;
      option.textContent = hora;
      time.appendChild(option);
    });
    if (data.dia_bloqueado) {
      time.innerHTML = '<option value="">Esta data está indisponível</option>';
      showToast('A Larissa não atende nesta data. Escolha outro dia.');
    } else if (!(data.horarios || []).length) {
      time.innerHTML = '<option value="">Nenhum horário disponível</option>';
    }
    time.disabled = false;
  } catch (error) {
    time.innerHTML = '<option value="">Erro ao carregar horários</option>';
    showToast(error.message);
  }
}

async function submitBooking() {
  const selectedService = parseService();
  if (!selectedService || !dateInput?.value || !time?.value) throw new Error('Escolha serviço, data e horário.');
  const result = await apiRequest('agendamento.php', {
    method: 'POST',
    body: JSON.stringify({
      servico_id: selectedService.id,
      data: dateInput.value,
      hora: time.value,
      nome: clientName.value.trim(),
      telefone: onlyDigits(clientPhone.value),
      consentimento_whatsapp: Boolean(whatsappConsent?.checked)
    })
  });
  lastBooking = result.agendamento;
  lastWhatsappUrl = buildWhatsappUrl();
  return result;
}


// =====================================================================
// BOOKING STEPS / ETAPAS DO AGENDAMENTO
// =====================================================================

// Shows only the requested step and updates the "1/4" indicator.
// Mostra apenas a etapa pedida e atualiza o indicador "1/4".
function setStep(stepNumber) {
  $$(".booking-step").forEach((step) => {
    const currentStep = Number(step.dataset.step);
    step.classList.toggle("active", currentStep === stepNumber);
  });

  if (stepIndicator) {
    stepIndicator.textContent = `${stepNumber}/${TOTAL_STEPS}`;
  }
}


// =====================================================================
// DATA HELPERS / FUNÇÕES AUXILIARES
// =====================================================================

// Reads the selected service. The option value looks like "Name|price|minutes".
// Lê o serviço escolhido. O value da opção tem o formato "Nome|preço|minutos".
function parseService() {
  if (!service || !service.value) return null;
  const id = Number(service.value);
  return servicesCache.find((item) => item.id === id) || null;
}

// "2026-11-05" -> "05/11/2026".
// "2026-11-05" -> "05/11/2026".
function formatDate(date) {
  if (!date) return "";

  // T12:00:00 avoids timezone shifts (see getDateError).
  // T12:00:00 evita mudanças de fuso (veja getDateError).
  const formattedDate = new Date(`${date}T12:00:00`);

  return new Intl.DateTimeFormat("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(formattedDate);
}

// 115 -> "R$ 115,00".
// 115 -> "R$ 115,00".
function formatPrice(price) {
  return price.toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
}

// Keeps only the digits of a text.
// Mantém apenas os dígitos de um texto.
function onlyDigits(value) {
  return String(value || "").replace(/\D/g, "");
}

// Applies the phone mask: (11) 99999-9999.
// Aplica a máscara de telefone: (11) 99999-9999.
function formatPhone(value) {
  const digits = onlyDigits(value).slice(0, 11);

  if (digits.length <= 2) return digits;
  if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;

  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

// Checks name, phone and consent. Shows a message and returns false on the first problem.
// Confere nome, telefone e autorização. Mostra uma mensagem e devolve false no primeiro problema.
function validateClientData() {
  const name = clientName?.value.trim() || "";
  const phoneDigits = onlyDigits(clientPhone?.value);

  if (name.length < 3) {
    showToast("Informe seu nome.");
    clientName?.focus();
    return false;
  }

  // Area code (DDD) must be 11-99; 11-digit numbers are mobile and must start with 9 (NEW).
  // O DDD precisa ser 11-99; números de 11 dígitos são celulares e começam com 9 (NOVO).
  const validLength = phoneDigits.length === 10 || phoneDigits.length === 11;
  const validAreaCode = /^[1-9][1-9]/.test(phoneDigits);
  const validMobile = phoneDigits.length === 10 || phoneDigits[2] === "9";

  if (!validLength || !validAreaCode || !validMobile) {
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

// Fills the review screen (step 3). textContent is used on purpose: it never runs HTML,
// so a malicious name cannot inject code (anti-XSS).
// Preenche a tela de revisão (etapa 3). textContent é usado de propósito: nunca executa HTML,
// então um nome malicioso não consegue injetar código (proteção contra XSS).
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
  if (reviewService) reviewService.textContent = selectedService.nome;
  if (reviewDate) reviewDate.textContent = formatDate(dateInput.value);
  if (reviewTime) reviewTime.textContent = time.value;
  if (reviewPrice) reviewPrice.textContent = formatPrice(selectedService.preco);
}


// =====================================================================
// WHATSAPP
// =====================================================================

// Builds the wa.me link with the booking details. Returns "" if something is missing.
// Monta o link wa.me com os dados do agendamento. Devolve "" se algo estiver faltando.
function buildWhatsappUrl() {
  const selectedService = parseService();
  if (!selectedService || !dateInput?.value || !time?.value || !clientName?.value || !clientPhone?.value || !lastBooking) return "";
  const message = `Olá, Larissa! Meu agendamento foi registrado no site.\n\nCódigo: ${lastBooking.id}\nCliente: ${clientName.value.trim()}\nWhatsApp: ${clientPhone.value.trim()}\nServiço: ${selectedService.nome}\nData: ${formatDate(dateInput.value)}\nHorário: ${time.value}\nValor: ${formatPrice(selectedService.preco)}\nStatus: Pendente de confirmação.`;
  return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
}

// Opens a link in a new tab, without giving it access to this page (noopener).
// Abre um link em nova aba, sem dar a ela acesso a esta página (noopener).
function openWhatsapp(url) {
  if (!url) return;
  window.open(url, "_blank", "noopener,noreferrer");
}


// =====================================================================
// PHONE MASK / MÁSCARA DO WHATSAPP DA CLIENTE
// =====================================================================

if (clientPhone) {
  clientPhone.addEventListener("input", () => {
    clientPhone.value = formatPhone(clientPhone.value);
  });
}

if (service) service.addEventListener("change", loadAvailableTimes);
if (dateInput) dateInput.addEventListener("change", loadAvailableTimes);


// =====================================================================
// CONTINUE BUTTONS / BOTÕES CONTINUAR
// =====================================================================

// Step 1 -> 2: service, date and time must be valid.
// Etapa 1 -> 2: serviço, data e horário precisam estar válidos.
if (continueBtn) {
  continueBtn.addEventListener("click", () => {
    if (!parseService() || !time?.value) {
      showToast("Escolha o serviço, a data e o horário.");
      return;
    }

    // Typed dates can ignore min/max, so we validate them here (NEW).
    // Datas digitadas podem ignorar min/max, então validamos aqui (NOVO).
    const dateError = getDateError(dateInput?.value);
    if (dateError) {
      showToast(dateError);
      dateInput?.focus();
      return;
    }

    setStep(2);
  });
}

// Step 2 -> 3: client data must be valid; then show the review.
// Etapa 2 -> 3: os dados da cliente precisam estar válidos; depois mostra a revisão.
if (clientContinueBtn) {
  clientContinueBtn.addEventListener("click", () => {
    if (!validateClientData()) return;
    fillReview();
    setStep(3);
  });
}


// =====================================================================
// BACK BUTTONS / BOTÕES VOLTAR
// =====================================================================

if (backToScheduleBtn) {
  backToScheduleBtn.addEventListener("click", () => setStep(1));
}

if (backBtn) {
  backBtn.addEventListener("click", () => setStep(2));
}


// =====================================================================
// CONFIRM / OPEN WHATSAPP / CONFIRMAR / ABRIR WHATSAPP
// =====================================================================

if (confirmBtn) {
  confirmBtn.addEventListener("click", async () => {
    const dateError = getDateError(dateInput?.value);
    if (dateError) { showToast(dateError); setStep(1); return; }
    if (!validateClientData()) return;
    confirmBtn.disabled = true;
    const originalText = confirmBtn.textContent;
    confirmBtn.textContent = "Registrando...";
    try {
      const result = await submitBooking();
      setStep(4);
      showToast(`Agendamento #${result.agendamento.id} registrado como pendente.`);
    } catch (error) {
      showToast(error.message);
      if (error.message.toLowerCase().includes('horário') || error.message.toLowerCase().includes('data')) {
        await loadAvailableTimes();
        setStep(1);
      }
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.textContent = originalText;
    }
  });
}

// Backup button on step 4: opens the same link again.
// Botão reserva da etapa 4: abre o mesmo link de novo.
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

// "Talk on WhatsApp" button in the contact section (general question, no booking).
// Botão "Falar no WhatsApp" da seção de contato (dúvida geral, sem agendamento).
if (whatsappContactBtn) {
  whatsappContactBtn.addEventListener("click", () => {
    const message = "Olá, Larissa! Vim pelo site e gostaria de tirar uma dúvida sobre os serviços.";
    const url = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
    openWhatsapp(url);
  });
}


// =====================================================================
// NEW BOOKING / NOVO AGENDAMENTO
// =====================================================================

// Clears every field and goes back to step 1.
// Limpa todos os campos e volta para a etapa 1.
if (newBookingBtn) {
  newBookingBtn.addEventListener("click", () => {
    if (service) service.value = "";
    if (dateInput) dateInput.value = "";
    if (time) time.value = "";
    if (clientName) clientName.value = "";
    if (clientPhone) clientPhone.value = "";
    if (whatsappConsent) whatsappConsent.checked = false;

    lastWhatsappUrl = "";
    lastBooking = null;
    if (time) { time.innerHTML = '<option value="">Escolha primeiro o serviço e a data</option>'; time.disabled = true; }
    setStep(1);
  });
}


// =====================================================================
// SERVICE CARDS / CARDS DOS SERVIÇOS
// =====================================================================

// Clicking a card pre-selects its service and scrolls to the booking form.
// Clicar em um card pré-seleciona o serviço e rola até o formulário de agendamento.
$$(".service-card").forEach((card) => {
  const button = card.querySelector("button");
  if (!button) return;

  button.addEventListener("click", () => {
    if (service && card.dataset.service) {
      const serviceName = card.dataset.service.split("|")[0];
      const found = servicesCache.find((item) => item.nome === serviceName);
      if (found) { service.value = String(found.id); loadAvailableTimes(); }
    }

    const bookingSection = $("#agendamento");

    if (bookingSection) {
      bookingSection.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
    }

    setStep(1);

    // Wait for the scroll to finish before focusing the date field.
    // Espera a rolagem terminar antes de focar no campo de data.
    setTimeout(() => dateInput?.focus(), 700);
  });
});


// =====================================================================
// SCROLL-CONTROLLED ABOUT VIDEO / VÍDEO DO SOBRE CONTROLADO PELO SCROLL
// CHANGED: the animation loop now runs ONLY while the section is on screen
// (before, it ran forever, even far from the video, wasting battery).
// ALTERADO: o loop de animação agora roda SÓ enquanto a seção está na tela
// (antes rodava para sempre, mesmo longe do vídeo, gastando bateria).
// =====================================================================

const aboutSection = $("#sobre");
const aboutVideo = $("#aboutScrollVideo");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

if (aboutSection && aboutVideo && !reduceMotion) {
  let duration = 0;       // video length in seconds / duração do vídeo em segundos
  let targetTime = 0;     // where the video should be / onde o vídeo deveria estar
  let smoothTime = 0;     // eased position, avoids jumps / posição suavizada, evita saltos
  let rafId = null;       // animation frame id (null = loop stopped) / id do frame (null = loop parado)
  let isVisible = false;  // is the section near the screen? / a seção está perto da tela?

  // 0 to 1: how far the user scrolled through the about section.
  // 0 a 1: quanto o usuário já rolou pela seção "sobre".
  const getProgress = () => {
    const rect = aboutSection.getBoundingClientRect();
    const scrollableDistance = Math.max(1, aboutSection.offsetHeight - window.innerHeight);
    const travelled = Math.min(Math.max(-rect.top, 0), scrollableDistance);
    return travelled / scrollableDistance;
  };

  // Converts scroll progress into a time inside the video.
  // Converte o progresso da rolagem em um tempo dentro do vídeo.
  const updateTargetTime = () => {
    if (!duration) return;
    targetTime = getProgress() * Math.max(0, duration - 0.05);
  };

  // One animation frame: move the video a bit closer to the target time.
  // Um frame da animação: aproxima o vídeo um pouco do tempo-alvo.
  const animateVideo = () => {
    // Stop the loop when off screen or before the video metadata is ready.
    // Para o loop quando está fora da tela ou antes dos metadados do vídeo estarem prontos.
    if (!isVisible || !duration) {
      rafId = null;
      return;
    }

    updateTargetTime();
    smoothTime += (targetTime - smoothTime) * 0.09;

    if (Number.isFinite(smoothTime) && Math.abs(aboutVideo.currentTime - smoothTime) > 0.01) {
      try {
        aboutVideo.currentTime = smoothTime;
      } catch (error) {
        // Some browsers block very fast seeks while the video is still loading.
        // Alguns navegadores bloqueiam seeks muito rápidos enquanto o vídeo carrega.
      }
    }

    rafId = requestAnimationFrame(animateVideo);
  };

  // Starts the loop only if it is not running yet and the section is visible.
  // Inicia o loop só se ainda não estiver rodando e a seção estiver visível.
  const startLoop = () => {
    if (rafId === null && isVisible && duration) {
      rafId = requestAnimationFrame(animateVideo);
    }
  };

  const stopLoop = () => {
    if (rafId !== null) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }
  };

  // Called when the video metadata is available (duration known).
  // Chamada quando os metadados do vídeo estão disponíveis (duração conhecida).
  const prepareVideo = () => {
    duration = Number.isFinite(aboutVideo.duration) ? aboutVideo.duration : 0;
    aboutVideo.pause();
    updateTargetTime();
    smoothTime = targetTime;
    startLoop();
  };

  // Watch the section: loop on while it is near the screen, off otherwise.
  // The 200px margin starts the loop slightly before the section appears.
  // Observa a seção: loop ligado enquanto ela está perto da tela, desligado fora dela.
  // A margem de 200px inicia o loop um pouco antes de a seção aparecer.
  new IntersectionObserver(
    ([entry]) => {
      isVisible = entry.isIntersecting;
      if (isVisible) startLoop();
      else stopLoop();
    },
    { rootMargin: "200px 0px" }
  ).observe(aboutSection);

  aboutVideo.addEventListener("loadedmetadata", prepareVideo);

  // If the metadata already loaded before this script ran, prepare right away.
  // Se os metadados já carregaram antes deste script rodar, prepara na hora.
  if (aboutVideo.readyState >= 1) {
    prepareVideo();
  }
}


// =====================================================================
// REVEAL ON SCROLL / ANIMAÇÕES AO ROLAR
// =====================================================================

const revealElements = $$(".reveal");

if (revealElements.length > 0 && "IntersectionObserver" in window) {
  const revealObserver = new IntersectionObserver(
    (entries, observer) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          // Show the element and stop watching it (animates only once).
          // Mostra o elemento e para de observá-lo (anima uma vez só).
          entry.target.classList.add("visible");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12 }
  );

  revealElements.forEach((element) => revealObserver.observe(element));
} else {
  // Old browsers without IntersectionObserver: show everything, never leave content hidden (NEW).
  // Navegadores antigos sem IntersectionObserver: mostra tudo, nunca deixa conteúdo escondido (NOVO).
  revealElements.forEach((element) => element.classList.add("visible"));
}


// =====================================================================
// ACTIVE MENU LINK / MENU ATIVO CONFORME A SEÇÃO
// =====================================================================

const sections = $$("main section[id]");
const navLinks = $$(".desktop-nav a");

if (sections.length > 0 && navLinks.length > 0 && "IntersectionObserver" in window) {
  const sectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        // Highlight only the link of the section crossing the middle of the screen.
        // Destaca só o link da seção que está cruzando o meio da tela.
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


// =====================================================================
// SMOOTH ANCHOR LINKS / LINKS COM ROLAGEM SUAVE
// =====================================================================

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


// =====================================================================
// START / INICIAR SISTEMA
// =====================================================================

// Show step 1 when the page loads.
// Mostra a etapa 1 quando a página carrega.
setStep(1);
(async () => {
  try {
    await loadCsrf();
    await loadServices();
  } catch (error) {
    showToast('Não foi possível conectar ao sistema de agendamento.');
    if (service) service.innerHTML = '<option value="">Sistema temporariamente indisponível</option>';
  }
})();
console.log("Larissa Ferreira Beauty carregado.");