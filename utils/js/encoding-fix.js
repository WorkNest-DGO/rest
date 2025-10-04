;(function(){
  const map = new Map([
    ['á','á'],['Ã©','é'],['Ã­','í'],['Ã³','ó'],['Ãº','ú'],['Ãñ','ñ'],['Ã±','ñ'],
    ['Ã�','Á'],['Ã‰','É'],['Ã�','Í'],['Ã“','Ó'],['Ãš','Ú'],['Ã‘','Ñ'],
    ['Â¡','¡'],['Â¿','¿'],['Âº','º'],['Âª','ª'],['Â·','·'],['Â«','«'],['Â»','»'],
    ['â€“','–'],['â€”','—'],['â€˜','‘'],['â€™','’'],['â€œ','“'],['â€�','”'],['â€¦','…'],
    ['Ã¼','ü'],['Ãœ','Ü'],['Â','']
  ]);

  function fixString(s){
    if (!s || typeof s !== 'string') return s;
    // Reemplazos comunes de mojibake
    let out = s;
    map.forEach((v,k)=>{ out = out.split(k).join(v); });
    return out;
  }

  function walk(node){
    if (!node) return;
    const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT, null, false);
    let n;
    while ((n = walker.nextNode())) {
      const fixed = fixString(n.nodeValue);
      if (fixed !== n.nodeValue) n.nodeValue = fixed;
    }
  }

  function handleMutations(muts){
    muts.forEach(m=>{
      m.addedNodes && m.addedNodes.forEach(n=>{
        if (n.nodeType === 3) { // Text
          const fixed = fixString(n.nodeValue);
          if (fixed !== n.nodeValue) n.nodeValue = fixed;
        } else if (n.nodeType === 1) {
          walk(n);
        }
      });
    });
  }

  function init(){
    try { walk(document.body); } catch(_){ }
    try {
      const obs = new MutationObserver(handleMutations);
      obs.observe(document.body, { childList: true, subtree: true });
    } catch(_){ }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
