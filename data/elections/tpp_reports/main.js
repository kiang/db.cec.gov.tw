/* TPP 2026 Election Strategy Report - Client-side Renderer */
(function () {
  'use strict';
  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const h = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
  const fmt = n => Number(n).toLocaleString();
  const chg = v => (v >= 0 ? '+' : '') + v;
  const cls = v => v >= 0 ? 'pos' : 'neg';

  let DATA = null;

  async function init() {
    const params = new URLSearchParams(location.search);
    let zone = params.get('zone');
    // Load zone index
    const zIdx = await (await fetch('zones.json')).json();
    buildNav(zIdx, zone);
    if (!zone && zIdx.length > 0) zone = zIdx[0].code;
    if (!zone) { $('#app').innerHTML = '<p>無資料</p>'; return; }
    try {
      DATA = await (await fetch(zone + '.json')).json();
      render();
    } catch (e) {
      $('#app').innerHTML = '<p>無法載入 ' + h(zone) + ' 的資料</p>';
    }
  }

  function buildNav(zones, current) {
    const grouped = {};
    zones.forEach(z => { (grouped[z.city] = grouped[z.city] || []).push(z); });
    let opts = '';
    for (const [city, zs] of Object.entries(grouped)) {
      opts += `<optgroup label="${h(city)}">`;
      zs.forEach(z => {
        const sel = z.code === current ? ' selected' : '';
        opts += `<option value="${h(z.code)}"${sel}>${h(z.zone)}（${h(z.areas)}）</option>`;
      });
      opts += '</optgroup>';
    }
    const nav = document.createElement('div');
    nav.className = 'nav-bar';
    nav.innerHTML = `<label>選擇選區：</label><select id="zone-sel">${opts}</select>`;
    document.body.insertBefore(nav, $('#app'));
    $('#zone-sel').addEventListener('change', function () {
      location.search = '?zone=' + this.value;
    });
  }

  function render() {
    const d = DATA;
    const app = $('#app');
    const otherRate = Math.max(0, (100 - d.rates.dpp_2024 - d.rates.kmt_2024 - d.rates.tpp_2024)).toFixed(2);
    const passThreshold = d.est_base >= d.threshold_2022;
    const canTwo = d.est_base >= d.threshold_2022 * 2;

    app.innerHTML = `
    <header>
      <h1>2026 議員選舉備戰報告 — ${h(d.zone)}</h1>
      <div class="sub">台灣民眾黨 ｜ ${h(d.areas)} ｜ ${h(d.code)}</div>
    </header>

    <section>
      <h2>一、選區基本資訊與選情概覽</h2>
      <div class="grid">
        <div class="card"><div class="lb">村里數</div><div class="val">${d.cunli_count}</div></div>
        ${d.seats_2022 ? `<div class="card"><div class="lb">2022 應選席次</div><div class="val">${d.seats_2022}</div><div class="dt">參選 ${d.cands_2022} 人</div></div>
        <div class="card"><div class="lb">2022 當選門檻</div><div class="val">${fmt(d.threshold_2022)} 票</div><div class="dt">${h(d.threshold_who)}</div></div>` : ''}
        <div class="card"><div class="lb">2024 政黨票</div><div class="val">${d.rates.tpp_2024}%</div><div class="dt">${fmt(d.votes.tpp_2024)} 票</div></div>
        <div class="card"><div class="lb">政黨票成長</div><div class="val ${cls(d.rates.change)}">${chg(d.rates.change)}</div><div class="dt">2020 ${d.rates.tpp_2020}% → 2024 ${d.rates.tpp_2024}%</div></div>
        <div class="card"><div class="lb">2024 總統票</div><div class="val">${d.rates.tpp_2024p}%</div><div class="dt">${fmt(d.votes.tpp_2024p)} 票</div></div>
      </div>
      <div class="bar-wrap">
        <div style="font-size:.82em;color:var(--text2);margin-bottom:4px">2024 三黨政黨票版圖</div>
        <div class="bar">
          <div class="dpp" style="width:${d.rates.dpp_2024}%">${d.rates.dpp_2024}%</div>
          <div class="kmt" style="width:${d.rates.kmt_2024}%">${d.rates.kmt_2024}%</div>
          <div class="tpp" style="width:${d.rates.tpp_2024}%">${d.rates.tpp_2024}%</div>
          <div class="oth" style="width:${otherRate}%"></div>
        </div>
        <div class="legend">
          <span class="dpp">民進黨 ${d.rates.dpp_2024}%</span>
          <span class="kmt">國民黨 ${d.rates.kmt_2024}%</span>
          <span class="tpp">民眾黨 ${d.rates.tpp_2024}%</span>
          <span class="oth">其他 ${otherRate}%</span>
        </div>
      </div>
      <div style="font-size:.82em;color:var(--text2);margin-top:12px">
        歷屆得票：2020政黨 ${d.rates.tpp_2020}%（${fmt(d.votes.tpp_2020)}票）→ 2022議員 ${d.rates.tpp_2022c}%（${fmt(d.votes.tpp_2022c)}票）→ 2024政黨 ${d.rates.tpp_2024}%（${fmt(d.votes.tpp_2024)}票）→ 2024總統 ${d.rates.tpp_2024p}%（${fmt(d.votes.tpp_2024p)}票）
      </div>
      ${d.multi_town ? renderTownSubs(d.town_subtotals) : ''}
    </section>

    <section>
      <h2>二、2022 議員選舉結果分析</h2>
      ${renderCands2022(d)}
    </section>

    <section>
      <h2>三、2026 議員選票預估</h2>
      <div style="font-size:.82em;color:var(--text2);margin-bottom:12px">以 2020→2022 政黨票轉議員票轉換率（${(d.conversion_2020_2022 * 100).toFixed(1)}%）× 2024 政黨票 × 投票率比（${(d.turnout_ratio * 100).toFixed(1)}%）推算</div>
      <div class="est-box">
        <div class="row hl"><span>基本盤預估</span><span>${fmt(d.est_base)} 票</span></div>
        <div class="row"><span>樂觀預估（含總統票外溢）</span><span>${fmt(d.est_opt)} 票</span></div>
        ${d.threshold_2022 ? `<div class="row"><span>2022 當選門檻</span><span>${fmt(d.threshold_2022)} 票</span></div>` : ''}
        ${d.threshold_2022 ? renderGauge(d) : ''}
        ${d.threshold_2022 ? (passThreshold
          ? `<div class="verdict pass">✓ 基本盤已超過 2022 當選門檻（餘裕 +${fmt(d.est_base - d.threshold_2022)} 票）${canTwo ? '<br>✓ 預估票數可能支撐 2 席，可評估提名 2 人策略' : ''}</div>`
          : `<div class="verdict need">△ 基本盤距 2022 門檻尚差 ${fmt(d.threshold_2022 - d.est_base)} 票${d.est_opt >= d.threshold_2022 ? '<br>✓ 樂觀預估可達門檻，關鍵在候選人能否承接政黨支持' : ''}</div>`)
        : ''}
      </div>
      ${renderTopEst(d)}
    </section>

    <section>
      <h2>四、村里分級經營策略</h2>
      ${renderTiers(d)}
    </section>

    <section>
      <h2>五、2022 現任議員與地方經營基礎</h2>
      ${renderCouncillors(d)}
    </section>

    <section>
      <h2>六、村里完整數據表</h2>
      ${renderFullTable(d)}
    </section>

    <section>
      <h2>七、2026 備戰重點行動</h2>
      <div class="actions">${renderActions(d)}</div>
    </section>

    <footer>
      資料來源：中央選舉委員會選舉資料庫 ｜ 涵蓋選舉：2020不分區、2022議員、2024不分區、2024總統<br>
      目標選舉：2026 縣市議員 ｜ 報告產生時間：${h(d.generated)}<br>
      預估方法：以 2020→2022 政黨票轉議員票轉換率，套用 2024 政黨票推算 2026 議員票。<br>
      注意：預估票數僅供參考，實際得票受候選人特質、選情變化、投票率及對手策略等多重因素影響。<br>
      資料整理：<a href="https://facebook.com/k.olc.tw" target="_blank" rel="noopener">江明宗</a>
    </footer>`;

    // Enable sorting
    $$('th[data-sort]').forEach(th => {
      th.addEventListener('click', () => sortTable(th));
    });
  }

  function renderGauge(d) {
    const max = Math.max(d.est_opt, d.threshold_2022) * 1.1;
    const baseW = (d.est_base / max * 100).toFixed(1);
    const optW = (d.est_opt / max * 100).toFixed(1);
    const threshPos = (d.threshold_2022 / max * 100).toFixed(1);
    return `<div style="margin:10px 0">
      <div class="gauge"><div class="fill" style="width:${optW}%;background:${d.est_opt >= d.threshold_2022 ? '#A5D6A7' : '#FFCC80'}"></div><div class="fill" style="width:${baseW}%;background:var(--tpp);position:absolute;top:0;left:0;height:100%"></div><div class="marker" style="left:${threshPos}%" title="2022門檻"></div></div>
      <div style="display:flex;justify-content:space-between;font-size:.72em;color:var(--text2)"><span>0</span><span style="position:relative;left:${threshPos - 50}%">▲門檻 ${fmt(d.threshold_2022)}</span><span>${fmt(Math.round(max))}</span></div>
    </div>`;
  }

  function renderTownSubs(subs) {
    if (!subs || subs.length === 0) return '';
    let rows = subs.map(t => `<tr><td>${h(t.name)}</td><td>${t.cunlis}</td><td>${t.r2020}%</td><td>${t.r2024}%</td><td class="${cls(t.chg)}">${chg(t.chg)}</td><td>${t.r2024p}%</td></tr>`).join('');
    return `<table style="margin-top:14px"><tr><th style="text-align:left">區域</th><th>里數</th><th>2020政黨</th><th>2024政黨</th><th>成長</th><th>2024總統</th></tr>${rows}</table>`;
  }

  function renderCands2022(d) {
    if (d.candidates_2022.length === 0) return '<p>（本區無 2022 議員選舉資料）</p>';
    let rows = d.candidates_2022.map(c => {
      const isTpp = c.party === '台灣民眾黨';
      return `<tr class="${isTpp ? 'tpp-row' : ''}"><td style="text-align:left">${c.no}</td><td style="text-align:left">${h(c.name)}</td><td style="text-align:left">${h(c.party)}</td><td>${fmt(c.votes)}</td><td>${c.elected ? '<span class="elected">當選</span>' : ''}</td></tr>`;
    }).join('');
    let html = `<table><tr><th style="text-align:left">序</th><th style="text-align:left">姓名</th><th style="text-align:left">政黨</th><th>得票數</th><th>結果</th></tr>${rows}</table>`;

    if (d.tpp_cands_2022.length > 0) {
      const tppTotal = d.tpp_cands_2022.reduce((s, c) => s + c.votes, 0);
      const tppElected = d.tpp_cands_2022.filter(c => c.elected).length;
      html += `<div style="margin-top:14px"><strong>民眾黨候選人：</strong>提名 ${d.tpp_cands_2022.length} 人，當選 ${tppElected} 人，合計 ${fmt(tppTotal)} 票`;
      d.tpp_cands_2022.forEach(c => {
        html += `<br>${h(c.name)}：${fmt(c.votes)} 票（${c.elected ? '當選' : '落選'}，${c.gap >= 0 ? '超過門檻 +' + c.gap : '距門檻差 ' + Math.abs(c.gap)} 票）`;
      });
      html += '</div>';
      if (d.conversion_rate > 0) {
        const level = d.conversion_rate < 50 ? 'low' : d.conversion_rate < 80 ? 'mid' : 'good';
        const msg = d.conversion_rate < 50 ? '轉換率偏低，大量政黨票支持者未投給議員候選人，2026 需強化候選人知名度與政黨連結。'
          : d.conversion_rate < 80 ? '轉換率中等，仍有提升空間。' : '轉換率良好，候選人有效承接政黨票。';
        html += `<div class="conv ${level}"><strong>議員票 vs 政黨票轉換率：${d.conversion_rate}%</strong>（2022議員票 ${fmt(tppTotal)} ÷ 2020政黨票 ${fmt(d.votes.tpp_2020)}）<br>${msg}</div>`;
      }
    } else {
      html += '<p style="margin-top:10px">2022 年本區無民眾黨議員候選人，2026 年需從零建立候選人知名度。</p>';
    }
    return html;
  }

  function renderTopEst(d) {
    const top = d.cunlis.slice().sort((a, b) => b.est - a.est).slice(0, 10);
    const topTotal = top.reduce((s, c) => s + c.est, 0);
    const pct = d.est_base > 0 ? (topTotal / d.est_base * 100).toFixed(1) : 0;
    let rows = top.map(c => `<tr><td>${h(c.label)}</td><td>${fmt(c.est)}</td><td>${fmt(c.estO)}</td></tr>`).join('');
    return `<p style="font-weight:600;margin-bottom:6px">預估票數 TOP 10</p>
      <table><tr><th style="text-align:left">村里</th><th>基本盤</th><th>樂觀</th></tr>${rows}
      <tr class="sub-row"><td>TOP 10 合計</td><td>${fmt(topTotal)}</td><td>佔 ${pct}%</td></tr></table>`;
  }

  function renderTiers(d) {
    const rc = d.rates.change;
    const groups = { A: [], B: [], C: [], D: [], E: [], W: [] };
    d.cunlis.forEach(c => {
      if (c.r2024 >= 25) groups.A.push(c);
      else if (c.chg > rc + 2) groups.B.push(c);
      else if (c.r2024 >= 15) groups.D.push(c);
      else groups.E.push(c);
      if (c.pvp > 3 && c.r2024p > 0) groups.C.push(c);
      if (c.chg < -2) groups.W.push(c);
    });
    groups.C.sort((a, b) => b.pvp - a.pvp);
    groups.D.sort((a, b) => b.est - a.est);

    const tr = d.turnout_ratio;
    let out = '';

    const tierDef = [
      { k: 'A', label: '核心票倉', desc: '2024政黨票 ≥ 25%', strategy: '鞏固基盤，維繫既有支持者，確保投票率。',
        render: c => `${h(c.label)}：政黨 ${c.r2024}%，總統 ${c.r2024p}%，預估 ${fmt(c.est)} 票` },
      { k: 'B', label: '高成長區', desc: '成長 > 全區平均+2%', strategy: '趁勢加碼，加強社區活動與里民互動，將動能轉為穩定支持。',
        render: c => `${h(c.label)}：${c.r2020}% → ${c.r2024}%（+${c.chg}），預估 ${fmt(c.est)} 票` },
      { k: 'C', label: '潛力轉化區', desc: '總統票 > 政黨票+3%', strategy: '選民「認人不認黨」，需強化政黨品牌與候選人連結。',
        render: c => { const ex = Math.round((c.r2024p - c.r2024) / 100 * c.total2024 * tr); return `${h(c.label)}：總統 ${c.r2024p}% vs 政黨 ${c.r2024}%（差距 +${c.pvp}，潛力 +${ex} 票）`; } },
      { k: 'D', label: '競爭區域', desc: '2024政黨票 15-25%', strategy: '積極拓展，安排服務據點或活動，有機會提升至核心票倉。',
        render: c => `${h(c.label)}：${c.r2024}%（${c.chg >= 0 ? '↑' : '↓'}${Math.abs(c.chg)}），預估 ${fmt(c.est)} 票` },
      { k: 'E', label: '低支持區域', desc: '2024政黨票 < 15%', strategy: '不宜投入過多資源，以議題經營建立接觸點，長期佈局。',
        render: c => `${h(c.label)}：${c.r2024}%（${c.chg >= 0 ? '↑' : '↓'}${Math.abs(c.chg)}），預估 ${fmt(c.est)} 票` },
      { k: 'W', label: '支持度下滑', desc: '成長 < -2%', strategy: '需實地訪查，了解退步原因，評估是否有在地議題未被回應。',
        render: c => `${h(c.label)}：${c.r2020}% → ${c.r2024}%（${c.chg}）` },
    ];

    tierDef.forEach(t => {
      const items = groups[t.k];
      if (items.length === 0 && t.k !== 'A') return;
      const estSum = items.reduce((s, c) => s + c.est, 0);
      const contribLabel = t.k === 'C'
        ? `若能完全轉化，額外可得：約 ${fmt(items.reduce((s,c) => s + Math.round((c.r2024p - c.r2024)/100 * c.total2024 * tr), 0))} 票`
        : (items.length > 0 ? `預估可貢獻：約 ${fmt(estSum)} 票` : '');
      out += `<div class="tg tg-${t.k}">
        <h3><span class="tier tier-${t.k}">${t.k === 'W' ? '⚠' : t.k}</span> ${t.label}（${t.desc}）— ${items.length} 個村里</h3>
        <p class="st">${t.strategy}</p>
        ${contribLabel ? `<p class="ct">${contribLabel}</p>` : ''}
        ${items.length > 0 ? `<ul>${items.slice(0, t.k === 'C' ? 15 : 999).map(c => `<li>${t.render(c)}</li>`).join('')}</ul>` : '<p style="font-size:.82em;color:var(--text2)">（本區目前無此類村里）</p>'}
      </div>`;
    });
    return out;
  }

  function renderCouncillors(d) {
    if (d.elected_names.length > 0) {
      let html = `<p><strong>現任民眾黨議員：</strong>${h(d.elected_names.join('、'))}</p>`;
      d.councillors.forEach(c => {
        html += `<div class="ccard"><h4>${h(c.name)}（${c.elected ? '當選' : '落選'}，得票 ${fmt(c.total_votes)}）</h4>
          <div style="font-size:.82em;color:var(--text2);margin-bottom:4px">票倉村里 TOP 5：</div>
          <ul>${c.top_cunlis.map(cl => `<li>${h(cl.cunli)}：${fmt(cl.votes)} 票（2022議員 ${cl.rate_2022}% → 2024政黨 ${cl.rate_2024}%）</li>`).join('')}</ul></div>`;
      });
      html += '<p style="font-size:.85em;margin-top:10px">經營建議：以現任議員服務處為據點向周邊擴展；盤點任內服務成果作為競選素材；若規劃提名第二人需評估票源分散風險。</p>';
      return html;
    }
    if (d.councillors.length > 0) {
      let html = '<p>本區 2022 年無民眾黨當選議員，但有候選人參選紀錄：</p>';
      d.councillors.forEach(c => {
        html += `<div class="ccard"><h4>${h(c.name)}（落選，得票 ${fmt(c.total_votes)}）</h4>
          <ul>${c.top_cunlis.map(cl => `<li>${h(cl.cunli)}：${fmt(cl.votes)} 票</li>`).join('')}</ul></div>`;
      });
      html += '<p style="font-size:.85em">經營建議：檢討 2022 落選原因，評估是否再次提名或更換人選。2024 政黨票已大幅成長，新候選人有更高基本盤可運用。</p>';
      return html;
    }
    return '<p>本區 2022 年無民眾黨議員候選人參選紀錄。2026 年為首次提名，需從零建立候選人知名度。</p><p style="font-size:.85em">經營建議：提早佈局深入在地服務；優先經營 A 級與 B 級村里快速建立口碑；善用 2024 政黨票基礎連結政黨認同選民。</p>';
  }

  function renderFullTable(d) {
    const rc = d.rates.change;
    const tierOf = c => { let t = c.r2024 >= 25 ? 'A' : c.chg > rc + 2 ? 'B' : c.r2024 >= 15 ? 'D' : 'E'; if (c.chg < -2) t += '⚠'; return t; };
    const thRow = '<tr><th style="text-align:left" data-sort="str">村里</th><th data-sort="num">2024政黨</th><th data-sort="num">2024總統</th><th data-sort="num">成長</th><th data-sort="num">2022議員</th><th data-sort="num">預估票</th><th data-sort="num">樂觀</th><th data-sort="str">級</th></tr>';
    const mkRow = c => {
      const tier = tierOf(c);
      const tierBase = tier.replace('⚠', '');
      return `<tr><td>${h(c.name)}</td><td>${c.r2024}%</td><td>${c.r2024p}%</td><td class="${cls(c.chg)}">${chg(c.chg)}</td><td>${c.r2022c > 0 ? c.r2022c + '%' : '-'}</td><td>${fmt(c.est)}</td><td>${fmt(c.estO)}</td><td><span class="tier tier-${tierBase}">${tier}</span></td></tr>`;
    };

    if (d.multi_town) {
      let html = '';
      d.towns.forEach(tn => {
        const tc = d.cunlis.filter(c => c.town === tn).sort((a, b) => b.chg - a.chg);
        if (tc.length === 0) return;
        const tEst = tc.reduce((s, c) => s + c.est, 0);
        const tOpt = tc.reduce((s, c) => s + c.estO, 0);
        html += `<table><tr class="town-hdr"><td colspan="8">${h(tn)}</td></tr>${thRow}${tc.map(mkRow).join('')}<tr class="sub-row"><td>小計</td><td colspan="4"></td><td>${fmt(tEst)}</td><td>${fmt(tOpt)}</td><td></td></tr></table>`;
      });
      html += `<table><tr class="sub-row"><td>全區合計</td><td colspan="4"></td><td>${fmt(d.est_base)}</td><td>${fmt(d.est_opt)}</td><td></td></tr></table>`;
      return html;
    }
    const sorted = d.cunlis.slice().sort((a, b) => b.chg - a.chg);
    return `<table class="sortable">${thRow}${sorted.map(mkRow).join('')}<tr class="sub-row"><td>合計</td><td colspan="4"></td><td>${fmt(d.est_base)}</td><td>${fmt(d.est_opt)}</td><td></td></tr></table>`;
  }

  function renderActions(d) {
    let out = '';
    const top5 = d.cunlis.slice().sort((a, b) => b.est - a.est).slice(0, 5);
    const top5Names = top5.map(c => c.label).join('、');
    const top5Votes = top5.reduce((s, c) => s + c.est, 0);
    const rc = d.rates.change;
    const highGrowth = d.cunlis.filter(c => c.chg > rc + 2);
    const declining = d.cunlis.filter(c => c.chg < -2);
    const presStronger = d.cunlis.filter(c => c.pvp > 3 && c.r2024p > 0).sort((a, b) => b.pvp - a.pvp);

    if (d.threshold_2022 > 0) {
      if (d.est_base >= d.threshold_2022) {
        out += `<div class="act"><h4>當選可行性：高</h4><p>基本盤 ${fmt(d.est_base)} 票已超過 2022 門檻 ${fmt(d.threshold_2022)} 票，關鍵在於候選人能否有效承接政黨票。需確保 2026 轉換率達到 80% 以上。</p></div>`;
      } else {
        out += `<div class="act"><h4>當選可行性：需努力</h4><p>基本盤預估 ${fmt(d.est_base)} 票，距 2022 門檻 ${fmt(d.threshold_2022)} 票尚差 ${fmt(d.threshold_2022 - d.est_base)} 票。需提高候選人知名度、強化總統票→議員票轉化（潛力約 ${fmt(d.est_opt - d.est_base)} 票）、提高支持者投票率。</p></div>`;
      }
    }
    out += `<div class="act"><h4>重點經營村里</h4><p>${h(top5Names)}，預估貢獻約 ${fmt(top5Votes)} 票，應優先設立服務據點、安排候選人走訪、建立里民聯繫網絡。</p></div>`;
    if (presStronger.length > 0) {
      const psExtra = presStronger.reduce((s, c) => s + Math.round((c.r2024p - c.r2024) / 100 * c.total2024 * d.turnout_ratio), 0);
      out += `<div class="act"><h4>轉化潛力</h4><p>${h(presStronger.slice(0, 3).map(c => c.label).join('、'))} 等 ${presStronger.length} 個村里總統票顯著高於政黨票。若能有效轉化，額外可得約 ${fmt(psExtra)} 票。建議候選人以個人特質吸引支持，強調地方服務實績。</p></div>`;
    }
    if (highGrowth.length > 0) {
      out += `<div class="act"><h4>乘勝追擊</h4><p>${h(highGrowth.slice(0, 3).map(c => c.label).join('、'))} 等 ${highGrowth.length} 個高成長村里，支持度快速攀升，趁勢加強社區經營，固化成長動能。</p></div>`;
    }
    if (declining.length > 0) {
      out += `<div class="act"><h4>止血警示</h4><p>${h(declining.slice(0, 3).map(c => c.label).join('、'))} 等 ${declining.length} 個村里支持度下滑，需實地訪查退步原因。</p></div>`;
    }
    if (d.elected_names.length > 0) {
      out += `<div class="act"><h4>善用現任優勢</h4><p>現任議員 ${h(d.elected_names.join('、'))}，應盤點任內服務成果以政績爭取連任支持。評估服務處覆蓋範圍、是否增設據點、及提名第二席的票源分配策略。</p></div>`;
    }
    return out;
  }

  function sortTable(th) {
    const table = th.closest('table');
    const idx = Array.from(th.parentNode.children).indexOf(th);
    const type = th.dataset.sort;
    const tbody = table.querySelector('tbody') || table;
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('sub-row') && !r.classList.contains('town-hdr') && r.querySelector('td'));
    const asc = table.dataset.sc === String(idx) && table.dataset.sd === 'asc';
    table.dataset.sc = idx; table.dataset.sd = asc ? 'desc' : 'asc';
    rows.sort((a, b) => {
      let av = a.cells[idx]?.textContent.trim().replace(/[%,票+]/g, '') || '';
      let bv = b.cells[idx]?.textContent.trim().replace(/[%,票+]/g, '') || '';
      if (type === 'num') { av = parseFloat(av) || -999; bv = parseFloat(bv) || -999; }
      return (av < bv ? 1 : av > bv ? -1 : 0) * (asc ? -1 : 1);
    });
    rows.forEach(r => tbody.appendChild(r));
    th.parentNode.querySelectorAll('th').forEach((t, i) => {
      t.textContent = t.textContent.replace(/ [▲▼]$/, '');
      if (i === idx) t.textContent += asc ? ' ▲' : ' ▼';
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
