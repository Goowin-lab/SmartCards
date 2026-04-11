// Archivo sugerido: smartcards-plugin/includes/assets/js/sc-iap-diag.js
(function(){
  function log(obj){ 
    const el = document.getElementById('sc-iap-out');
    if (!el) return;
    el.textContent += (typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2)) + "\n";
  }
  function getDiag(){
    return {
      ua: navigator.userAgent,
      hasPurchases: !!window.Purchases,
      hasRCAliases: !!(window.revenuecat || window.RC),
      hasNativelyPurchases: (typeof window.NativelyPurchases === 'function'),
      hasNatively: !!(window.Natively || window.natively),
      keys: Object.keys(window).filter(k => /Purchases|revenuecat|RC|Natively/i.test(k)).sort()
    };
  }
  async function tryRC(){
    if (!window.Purchases) { log('❌ RevenueCat no está expuesto en WebView (window.Purchases).'); return; }
    try{
      if (typeof window.Purchases.getCustomerInfo === 'function'){
        const info = await window.Purchases.getCustomerInfo();
        log('✔ RC getCustomerInfo:'); log(info);
      }
      if (typeof window.Purchases.getOfferings === 'function'){
        const off = await window.Purchases.getOfferings();
        log('✔ RC offerings:'); log(off);
      }
    }catch(e){ log('⚠️ RC error: ' + String(e)); }
  }
  document.addEventListener('click', function(e){
    if (e.target && e.target.id === 'sc-iap-run'){
      e.preventDefault();
      const out = document.getElementById('sc-iap-out'); if (out) out.textContent = '';
      log('— Diagnóstico de entorno —'); log(getDiag()); tryRC();
      if (typeof window.__iapDiag === 'function'){ log('— __iapDiag() —'); log(window.__iapDiag()); }
      log('Tip: Si "hasPurchases" es false, activa "Expose Purchases to WebViews" en BuildNatively iOS y recompila.');
    }
  });
})();
