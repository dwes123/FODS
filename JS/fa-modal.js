/* File: /wp-content/themes/twentytwentytwo-child/js/fa-modal.js */

/**
 * Handles:
 * - Free Agent offer modal
 * - Roster moves (Promote/Option)
 * - Waiver actions (Waive/Claim)
 */
(function () {
  if (!window.faModalData) return;

  // ---------- Helpers ----------
  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

  function closest(el, selector) {
    while (el && el.nodeType === 1) {
      if (el.matches(selector)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function serialize(obj) {
    const fd = new FormData();
    Object.keys(obj).forEach((k) => {
      fd.append(k, obj[k]);
    });
    return fd;
  }

  async function postAjax(action, payload) {
    const body = serialize(Object.assign({ action }, payload));
    const res = await fetch(faModalData.ajax_url, { method: "POST", body });
    let data;
    try {
      data = await res.json();
    } catch (e) {
      data = { success: false, data: "Invalid server response." };
    }
    return data;
  }

  function addNotice(msg, type = "info") {
    let box = $("#roster-notices-container") || $("#waiver-notices-container");
    if (!box) {
      const target = document.body;
      box = document.createElement("div");
      box.id = "roster-notices-container";
      target.prepend(box);
    }
    const div = document.createElement("div");
    div.className = `notice-inline notice-${type}`;
    div.innerHTML = `
      <span class="notice-text">${msg}</span>
      <button type="button" class="notice-dismiss" aria-label="Dismiss">×</button>
    `;
    box.appendChild(div);
    setTimeout(() => div.remove(), 8000);
  }

  // Helper function to update the 40-Man column cell text
  function set40ManCell(row, valueText) {
    const cells = row.querySelectorAll("td");
    if (cells.length >= 5) {
      cells[4].textContent = valueText || "–";
    }
  }

  // Helper function to swap action buttons in a row
  function replaceActionButton(row, to) {
    const cell = row.querySelector(".player-action-cell");
    if (!cell) return;
    const waiveBtn = cell.querySelector(".waive-player-button");
    $$(".promote-40-button, .option-minors-button", cell).forEach((b) => b.remove());

    const meta = getRowMeta(row);
    if (!meta) return;

    if (to === "option") {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "button option-minors-button";
      btn.textContent = "Option to Minors";
      setBtnDataset(btn, meta);
      if (waiveBtn) cell.insertBefore(btn, waiveBtn);
      else cell.appendChild(btn);
    } else if (to === "promote") {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "button promote-40-button";
      btn.textContent = "Move to 40-Man";
      setBtnDataset(btn, meta);
      if (waiveBtn) cell.insertBefore(btn, waiveBtn);
      else cell.appendChild(btn);
    }
  }

  function getRowMeta(row) {
    const btn = row.querySelector(".promote-40-button, .option-minors-button, .waive-player-button") || row.querySelector("button");
    if (!btn) return null;
    return {
      playerId: btn.dataset.playerid,
      playerName: btn.dataset.playername,
      leagueId: btn.dataset.leagueid,
      teamId: btn.dataset.teamid,
    };
  }

  function setBtnDataset(btn, meta) {
      btn.dataset.playerid = meta.playerId;
      btn.dataset.playername = meta.playerName;
      if (meta.leagueId) btn.dataset.leagueid = meta.leagueId;
      if (meta.teamId) btn.dataset.teamid = meta.teamId;
  }

  // ---------- Modal elements ----------
  const modal = $("#fa-offer-modal");
  const modalTitle = $("#fa-modal-title");
  const modalForm = $("#fa-offer-form");
  const modalCloseX = $(".fa-modal-close");
  const modalCancel = $(".fa-modal-cancel");
  const modalMsg = $("#fa-modal-message");
  const inputPlayerId = $("#fa-modal-playerid");
  const inputLeagueId = $("#fa-modal-leagueid");
  const inputTeamId = $("#fa-modal-teamid");
  const inputNonce = $("#fa-modal-nonce");

  function openModal() {
    if (!modal) return;
    modal.classList.remove("fa-modal-hidden");
    document.body.classList.add("fa-modal-open");
  }
  function closeModal() {
    if (!modal) return;
    modal.classList.add("fa-modal-hidden");
    document.body.classList.remove("fa-modal-open");
    if (modalMsg) modalMsg.textContent = "";
    if (modalForm) modalForm.reset();
  }

  if (modalCloseX) modalCloseX.addEventListener("click", closeModal);
  if (modalCancel) modalCancel.addEventListener("click", closeModal);
  if (modal) {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeModal();
    });
  }

  // ---------- Event delegation for all site actions ----------
  // ---------- Event delegation for all site actions ----------
  document.addEventListener("click", async (e) => {
    const t = e.target; // 't' is the actual element that was clicked

    // --- Free Agent Actions ---
    if (t.matches(".fa-offer-button")) {
      e.preventDefault();
      // ... (logic is unchanged)
    }

    // --- Waiver Wire Actions ---
    if (t.matches(".claim-player-button")) {
        e.preventDefault();
        // ... (logic is unchanged)
    }

    // --- Roster Management Actions ---
    // The logic below is now more robust and reads data directly from the clicked button 't'

    if (t.matches(".promote-40-button")) {
      e.preventDefault();
      t.disabled = true;
      try {
        const resp = await postAjax("promote_to_40man", {
            nonce: faModalData.roster_move_nonce,
            player_id: t.dataset.playerid
        });
        if (resp.success) {
          const row = closest(t, "tr");
          if (row) {
            const tbody40 = $("#roster-40-tbody");
            if (tbody40) tbody40.appendChild(row);
            set40ManCell(row, "X");
            replaceActionButton(row, "option");
          }
          addNotice(`${t.dataset.playername} moved to 40-man.`, "success");
        } else {
          addNotice(resp.data || "Could not move to 40-man.", "error");
        }
      } catch (err) {
        addNotice("Network error moving to 40-man.", "error");
      } finally {
        t.disabled = false;
      }
      return;
    }

    if (t.matches(".option-minors-button")) {
      e.preventDefault();
      t.disabled = true;
      try {
        const resp = await postAjax("option_to_minors", {
            nonce: faModalData.roster_move_nonce,
            player_id: t.dataset.playerid
        });
        if (resp.success) {
          const row = closest(t, "tr");
          if (row) {
            const tbodyMin = $("#roster-minors-tbody");
            if (tbodyMin) tbodyMin.appendChild(row);
            set40ManCell(row, "–");
            replaceActionButton(row, "promote");
          }
          addNotice(`${t.dataset.playername} optioned to Minors.`, "success");
        } else {
          addNotice(resp.data || "Could not option player.", "error");
        }
      } catch (err) {
        addNotice("Network error optioning player.", "error");
      } finally {
        t.disabled = false;
      }
      return;
    }

    if (t.matches(".waive-player-button")) {
        e.preventDefault();
        if (!confirm(`Place ${t.dataset.playername} on waivers? They will be available for other teams to claim for 24 hours.`)) {
            return;
        }
        t.disabled = true;
        try {
            const resp = await postAjax("waive_player", {
                nonce: faModalData.drop_player_nonce,
                player_id: t.dataset.playerid,
                team_id: t.dataset.teamid,
                league_id: t.dataset.leagueid
            });
            if (resp.success) {
                const row = closest(t, "tr");
                if (row) row.remove();
                addNotice(resp.data || `${t.dataset.playername} placed on waivers.`, "success");
            } else {
                addNotice(resp.data || "Could not place player on waivers.", "error");
            }
        } catch (err) {
            addNotice("Network error placing player on waivers.", "error");
        } finally {
            t.disabled = false;
        }
        return;
    }

    // --- General UI Actions ---
    if (t.matches(".notice-dismiss")) {
      // ... (logic is unchanged)
    }
  });
  // ---------- Optional: smooth anchor nav for FA pagination (if present) ----------
  document.addEventListener("click", (e) => {
    const t = e.target;
    if (t.closest(".fa-pagination a")) {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  });
})();