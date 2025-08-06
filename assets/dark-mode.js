/**
 * Dark Mode Manager - Sistema CGLIC
 * Gerenciamento de tema escuro/claro com persistência
 * Data: 01/01/2025
 */

class DarkModeManager {
  constructor() {
    this.storageKey = 'cglic-theme';
    this.init();
  }

  /**
   * Inicializar o sistema de dark mode
   */
  init() {
    // Carregar tema salvo ou detectar preferência do sistema
    this.loadSavedTheme();
    
    // Configurar event listeners
    this.setupToggleButton();
    this.setupSystemPreferenceListener();
    this.setupKeyboardShortcut();
    
    // Debug info
    this.logThemeInfo();
  }

  /**
   * Carregar tema salvo ou detectar preferência do sistema
   */
  loadSavedTheme() {
    const savedTheme = localStorage.getItem(this.storageKey);
    
    if (savedTheme) {
      // Usar tema salvo
      this.setTheme(savedTheme);
    } else {
      // Detectar preferência do sistema
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      this.setTheme(prefersDark ? 'dark' : 'light');
    }
  }

  /**
   * Definir tema e salvar preferência
   * @param {string} theme - 'light' ou 'dark'
   */
  setTheme(theme) {
    // Validar tema
    if (!['light', 'dark'].includes(theme)) {
      console.warn('Tema inválido:', theme);
      return;
    }

    // Aplicar tema
    document.documentElement.setAttribute('data-theme', theme);
    
    // Salvar preferência
    localStorage.setItem(this.storageKey, theme);
    
    // Atualizar botão
    this.updateToggleButton(theme);
    
    // Notificar mudança
    this.notifyThemeChange(theme);
    
    // Log da mudança
    console.log(`🎨 Tema alterado para: ${theme}`);
  }

  /**
   * Alternar entre temas
   */
  toggleTheme() {
    const currentTheme = this.getCurrentTheme();
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    this.setTheme(newTheme);
  }

  /**
   * Obter tema atual
   * @returns {string} 'light' ou 'dark'
   */
  getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme') || 'light';
  }

  /**
   * Configurar botão de toggle
   */
  setupToggleButton() {
    const toggleBtn = document.getElementById('theme-toggle');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.toggleTheme();
      });
    }
  }

  /**
   * Atualizar aparência do botão de toggle
   * @param {string} theme - Tema atual
   */
  updateToggleButton(theme) {
    const toggleBtn = document.getElementById('theme-toggle');
    if (!toggleBtn) return;

    const icon = toggleBtn.querySelector('i');
    const text = toggleBtn.querySelector('span');

    if (icon) {
      // Alterar ícone
      icon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
      
      // Reinicializar ícones Lucide se disponível
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    }

    if (text) {
      // Alterar texto
      text.textContent = theme === 'dark' ? 'Modo Claro' : 'Modo Escuro';
    }

    // Adicionar classe para identificar estado
    toggleBtn.classList.toggle('theme-dark', theme === 'dark');
    toggleBtn.classList.toggle('theme-light', theme === 'light');
  }

  /**
   * Configurar listener para mudanças na preferência do sistema
   */
  setupSystemPreferenceListener() {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    
    mediaQuery.addEventListener('change', (e) => {
      // Só aplicar mudança do sistema se usuário não tiver preferência salva
      const hasSavedPreference = localStorage.getItem(this.storageKey);
      
      if (!hasSavedPreference) {
        this.setTheme(e.matches ? 'dark' : 'light');
        console.log('🔄 Tema alterado automaticamente pela preferência do sistema');
      }
    });
  }

  /**
   * Configurar atalho de teclado (Ctrl/Cmd + Shift + D)
   */
  setupKeyboardShortcut() {
    document.addEventListener('keydown', (e) => {
      // Ctrl/Cmd + Shift + D
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        this.toggleTheme();
        
        // Mostrar feedback visual
        this.showShortcutFeedback();
      }
    });
  }

  /**
   * Mostrar feedback visual para atalho de teclado
   */
  showShortcutFeedback() {
    const currentTheme = this.getCurrentTheme();
    const message = `Modo ${currentTheme === 'dark' ? 'Escuro' : 'Claro'} ativado`;
    
    // Criar toast temporário
    const toast = document.createElement('div');
    toast.className = 'theme-toggle-toast';
    toast.textContent = message;
    toast.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: var(--bg-card);
      color: var(--text-primary);
      padding: 12px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px var(--shadow-card);
      z-index: 10000;
      font-size: 14px;
      font-weight: 500;
      border: 1px solid var(--border-color);
      opacity: 0;
      transition: opacity 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // Mostrar e ocultar
    setTimeout(() => toast.style.opacity = '1', 10);
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, 2000);
  }

  /**
   * Notificar outros componentes sobre mudança de tema
   * @param {string} theme - Novo tema
   */
  notifyThemeChange(theme) {
    // Disparar evento customizado
    const event = new CustomEvent('themeChanged', {
      detail: { theme, timestamp: Date.now() }
    });
    
    document.dispatchEvent(event);
  }

  /**
   * Obter estatísticas de uso do tema
   */
  getThemeStats() {
    const stats = JSON.parse(localStorage.getItem('cglic-theme-stats') || '{}');
    return {
      totalToggles: stats.totalToggles || 0,
      lastToggle: stats.lastToggle || null,
      favoriteTheme: stats.favoriteTheme || 'light',
      sessionToggles: this.sessionToggles || 0
    };
  }

  /**
   * Salvar estatísticas de uso
   */
  saveThemeStats() {
    const currentStats = this.getThemeStats();
    const newStats = {
      totalToggles: currentStats.totalToggles + 1,
      lastToggle: new Date().toISOString(),
      favoriteTheme: this.getCurrentTheme(),
      sessionToggles: (this.sessionToggles || 0) + 1
    };
    
    localStorage.setItem('cglic-theme-stats', JSON.stringify(newStats));
    this.sessionToggles = newStats.sessionToggles;
  }

  /**
   * Log de informações do tema (debug)
   */
  logThemeInfo() {
    if (console.groupCollapsed) {
      console.groupCollapsed('🎨 Dark Mode Manager - Sistema CGLIC');
      console.log('Tema atual:', this.getCurrentTheme());
      console.log('Preferência do sistema:', window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      console.log('Tema salvo:', localStorage.getItem(this.storageKey));
      console.log('Estatísticas:', this.getThemeStats());
      console.log('Atalho: Ctrl/Cmd + Shift + D');
      console.groupEnd();
    }
  }

  /**
   * Resetar preferências (útil para debug/suporte)
   */
  resetPreferences() {
    localStorage.removeItem(this.storageKey);
    localStorage.removeItem('cglic-theme-stats');
    this.loadSavedTheme();
    console.log('🔄 Preferências de tema resetadas');
  }

  /**
   * Aplicar tema programaticamente (para integrações)
   * @param {string} theme - 'light', 'dark' ou 'auto'
   */
  applyTheme(theme) {
    if (theme === 'auto') {
      // Remover preferência salva e usar sistema
      localStorage.removeItem(this.storageKey);
      this.loadSavedTheme();
    } else {
      this.setTheme(theme);
    }
  }

  /**
   * Verificar se dark mode é suportado
   * @returns {boolean}
   */
  static isSupported() {
    return typeof window !== 'undefined' && 
           typeof document !== 'undefined' && 
           typeof localStorage !== 'undefined';
  }
}

// Classe para integração com gráficos Chart.js
class DarkModeChartIntegration {
  constructor(darkModeManager) {
    this.darkModeManager = darkModeManager;
    this.setupChartDefaults();
    this.setupThemeListener();
  }

  setupChartDefaults() {
    if (typeof Chart !== 'undefined') {
      // Configurações padrão para gráficos em dark mode
      this.updateChartDefaults();
    }
  }

  setupThemeListener() {
    document.addEventListener('themeChanged', (e) => {
      this.updateChartDefaults();
      // Atualizar gráficos existentes se necessário
      this.updateExistingCharts();
    });
  }

  updateChartDefaults() {
    if (typeof Chart === 'undefined') return;

    const isDark = this.darkModeManager.getCurrentTheme() === 'dark';
    
    Chart.defaults.color = isDark ? '#f7fafc' : '#2c3e50';
    Chart.defaults.borderColor = isDark ? '#4a5568' : '#dee2e6';
    Chart.defaults.backgroundColor = isDark ? '#2d3748' : '#ffffff';
  }

  updateExistingCharts() {
    // Atualizar gráficos existentes (implementar conforme necessário)
    if (typeof window.chartsInstances !== 'undefined') {
      window.chartsInstances.forEach(chart => {
        chart.update();
      });
    }
  }
}

// Inicialização automática quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
  // Verificar suporte
  if (!DarkModeManager.isSupported()) {
    console.warn('Dark Mode não suportado neste ambiente');
    return;
  }

  // Inicializar gerenciador
  window.darkModeManager = new DarkModeManager();
  
  // Integração com gráficos (se Chart.js estiver disponível)
  if (typeof Chart !== 'undefined') {
    window.chartIntegration = new DarkModeChartIntegration(window.darkModeManager);
  }

  // Expor funções globais para uso externo
  window.toggleTheme = () => window.darkModeManager.toggleTheme();
  window.setTheme = (theme) => window.darkModeManager.setTheme(theme);
  window.getCurrentTheme = () => window.darkModeManager.getCurrentTheme();
});

// Aplicar tema imediatamente para evitar flash
(function() {
  const savedTheme = localStorage.getItem('cglic-theme');
  if (savedTheme) {
    document.documentElement.setAttribute('data-theme', savedTheme);
  } else {
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
  }
})();

// Exportar para uso em módulos
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { DarkModeManager, DarkModeChartIntegration };
}