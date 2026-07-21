// https://datatables.net/reference/option/
var vespa_media_uploader = null;

function vespa_datatables_hu() {
  return {
    emptyTable: "Nincs rendelkezésre álló adat",
    info: "Találatok: _START_ - _END_ Összesen: _TOTAL_",
    infoEmpty: "Nulla találat",
    infoFiltered: "(_MAX_ összes rekord közül szűrve)",
    infoThousands: " ",
    lengthMenu: "_MENU_ találat oldalanként",
    loadingRecords: "Betöltés...",
    processing: "Feldolgozás...",
    search: "Keresés:",
    zeroRecords: "Nincs a keresésnek megfelelő találat",
    paginate: {
      first: "Első",
      previous: "Előző",
      next: "Következő",
      last: "Utolsó",
    },
    aria: {
      sortAscending: ": aktiválja a növekvő rendezéshez",
      sortDescending: ": aktiválja a csökkenő rendezéshez",
    },
    select: {
      rows: {
        _: "%d sor kiválasztva",
        1: "1 sor kiválasztva",
      },
      cells: {
        1: "1 cella kiválasztva",
        _: "%d cella kiválasztva",
      },
      columns: {
        1: "1 oszlop kiválasztva",
        _: "%d oszlop kiválasztva",
      },
    },
    buttons: {
      colvis: "Oszlopok",
      copy: "Másolás",
      copyTitle: "Vágólapra másolás",
      copySuccess: {
        _: "%d sor másolva",
        1: "1 sor másolva",
      },
      collection: "Gyűjtemény",
      colvisRestore: "Oszlopok visszaállítása",
      copyKeys:
        "Nyomja meg a CTRL vagy u2318 + C gombokat a táblázat adatainak a vágólapra másolásához.<br /><br />A megszakításhoz kattintson az üzenetre vagy nyomja meg az ESC billentyűt.",
      csv: "CSV",
      excel: "Excel",
      pageLength: {
        "-1": "Összes sor megjelenítése",
        _: "%d sor megjelenítése",
      },
      pdf: "PDF",
      print: "Nyomtat",
    },
    autoFill: {
      cancel: "Megszakítás",
      fill: "Összes cella kitöltése a következővel: <i>%d</i>",
      fillHorizontal: "Cellák vízszintes kitöltése",
      fillVertical: "Cellák függőleges kitöltése",
    },
    searchBuilder: {
      add: "Feltétel hozzáadása",
      button: {
        0: "Keresés konfigurátor",
        _: "Keresés konfigurátor (%d)",
      },
      clearAll: "Összes feltétel törlése",
      condition: "Feltétel",
      conditions: {
        date: {
          after: "Után",
          before: "Előtt",
          between: "Között",
          empty: "Üres",
          equals: "Egyenlő",
          not: "Nem",
          notBetween: "Kívül eső",
          notEmpty: "Nem üres",
        },
        number: {
          between: "Között",
          empty: "Üres",
          equals: "Egyenlő",
          gt: "Nagyobb mint",
          gte: "Nagyobb vagy egyenlő mint",
          lt: "Kissebb mint",
          lte: "Kissebb vagy egyenlő mint",
          not: "Nem",
          notBetween: "Kívül eső",
          notEmpty: "Nem üres",
        },
        string: {
          contains: "Tartalmazza",
          empty: "Üres",
          endsWith: "Végződik",
          equals: "Egyenlő",
          not: "Nem",
          notEmpty: "Nem üres",
          startsWith: "Kezdődik",
        },
      },
      data: "Adat",
      deleteTitle: "Feltétel törlése",
      logicAnd: "És",
      logicOr: "Vagy",
      title: {
        0: "Keresés konfigurátor",
        _: "Keresés konfigurátor (%d)",
      },
      value: "Érték",
    },
    searchPanes: {
      clearMessage: "Szűrők törlése",
      collapse: {
        0: "Szűrőpanelek",
        _: "Szűrőpanelek (%d)",
      },
      count: "{total}",
      countFiltered: "{shown} ({total})",
      emptyPanes: "Nincsenek szűrőpanelek",
      loadMessage: "Szűrőpanelek betöltése",
      title: "Aktív szűrőpanelek: %d",
    },
    datetime: {
      previous: "Előző",
      next: "Következő",
      hours: "Óra",
      minutes: "Perc",
      seconds: "Másodperc",
      amPm: ["de.", "du."],
    },
    editor: {
      close: "Bezárás",
      create: {
        button: "Új",
        title: "Új",
        submit: "Létrehozás",
      },
      edit: {
        button: "Módosítás",
        title: "Módosítás",
        submit: "Módosítás",
      },
      remove: {
        button: "Törlés",
        title: "Törlés",
        submit: "Törlés",
      },
    },
  };
}

var vespa_tables = {};

jQuery(document).ready(function () {
  // A szövegszerkesztőt a WordPress wp_editor() példányosítja (contest_editor.php).
  // Itt nem indítunk saját TinyMCE-t: a korábbi CDN-es init minden admin oldalon
  // lefutott, és eltörte a WP beépített szerkesztőjét a post.php-n.

  if (jQuery("#sport_id").length > 0) {
    jQuery("#sport_id").trigger("change");
  }

  jQuery("#doc_downloader").change(function () {
    var doctype = jQuery(this).val();
    var baseurl = jQuery(this).attr("data-url");

    if (doctype != "") {
      location.href =
        baseurl + doctype + "&contest_id=" + jQuery("#contest_id").val();
    }
  });

  jQuery(".entry-search").keyup(function () {
    var val = jQuery(this).val().toLowerCase();

    if (val == "") {
      jQuery(this).closest("table").find(".athlete-row").show();
    } else {
      jQuery(this)
        .closest("table")
        .find(".athlete-row")
        .each(function () {
          var has = false;

          jQuery(this)
            .find("td")
            .each(function () {
              var txt = jQuery(this).text().toLowerCase().trim();

              if (txt.indexOf(val) != -1) {
                has = true;
              }
            });

          if (has) {
            jQuery(this).show();
          } else {
            jQuery(this).hide();
          }
        });
    }
  });

  jQuery(".athlete-entry").click(function (e) {
    e.preventDefault();

    console.log("add to list");

    var tbl = jQuery(this).closest("table");
    var tr = jQuery(this).closest("tr");
    var aid = jQuery(this).attr("data-id");
    var contest_id = tbl.attr("data-contest_id");
    var contest_event_id = tbl.attr("data-contest_event_id");
    var event_id = tbl.attr("data-event_id");
    var row_id = tbl.attr("data-row-id");

    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: vitarex_vespa_ajaxurl,
      data: {
        action: "athletes_signup",
        contest_id: contest_id,
        event_id: event_id,
        contest_event_id: contest_event_id,
        athlete_ids: aid,
        school_id: 0,
      },
      success: function (resp) {
        if (resp.success) {
          // TODO: transfer this row to another table and increase the number
          jQuery(`#entered-num-${row_id}`).html(resp.pplnum);

          if (resp.action === "add") {
            jQuery(`#entered_list_${row_id} > tbody`).append(tr.clone(true));
            tr.remove();

            var ltr = jQuery(`#entered_list_${row_id} > tbody tr:last`);

            ltr.attr("style", "background-color:#b3ffd9");
          } else if (resp.action === "remove") {
            jQuery(`#can_enter_list_${row_id} > tbody`).append(tr.clone(true));
            tr.remove();

            var ltr = jQuery(`#can_enter_list_${row_id} > tbody tr:last`);

            ltr.attr("style", "background-color:#b3ffd9");
          }

          setTimeout(function () {
            ltr.attr("style", "");
          }, 1000);
        } else {
          alert(resp.message);
        }
      },
    });
  });

  function tovabbjuto_nevezes(e, adatok) {
    e.preventDefault();

    console.log("add to list");

    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: vitarex_vespa_ajaxurl,
      data: {
        action: "athletes_signup",
        contest_id: adatok["contest_id"],
        event_id: adatok["event_id"],
        contest_event_id: adatok["contest_event_id"],
        athlete_ids: adatok["aid"],
        school_id: adatok["school_id"],
      },
      success: function (resp) {
        if (resp.success) {
        } else {
        }
      },
    });
  }

  jQuery(".btn-signup").click(function () {
    jQuery(".vespa-race-item-athletes").hide();
    jQuery(this)
      .closest(".vespa-race-item")
      .next(".vespa-race-item-athletes")
      .show();
  });

  // remove user blocks
  jQuery('.user-edit-php h2:contains("A felhasználóról")').remove();

  //jQuery('.dpicker').inputmask("9999.99.99.");
  jQuery(".result-field").change(function () {
    var inp = jQuery(this);

    vespa_store_contest_event_result(inp);
  });

  jQuery("#list").DataTable({
    language: vespa_datatables_hu(),
  });

  jQuery(".datatables").each(function () {
    var source = jQuery(this).attr("data-source");
    var volid = jQuery(this).attr("data-volid");

    // jQuery('.datatables').columns.adjust().draw();
    var datatbl = jQuery(this).DataTable({
      language: vespa_datatables_hu(),
      columnDefs: [
        { width: "80", targets: 0 },
        { width: "150", targets: -1 },
      ],
      ajax: {
        url: vitarex_vespa_ajaxurl,
        type: "POST",
        data: function (d) {
          d.action = "list_" + source + "_items";

          if (source == "institution") {
            d.ins_type = jQuery("#ins_type").val();
            d.state = jQuery("#state").val();
            d.ins_city = jQuery("#ins_city").val();
            d.ins_name = jQuery("#ins_name").val();
            d.id_number = jQuery("#id_number").val();
            d.person_data = jQuery("#person_data").val();
          }
          if (source == "athletes") {
            d.school_id = jQuery("#school_id").val();
            d.birth_place = jQuery("#birth_place").val();
            d.birth_date = jQuery("#birth_date").val();
          }
        },
      },
      search: {
        return: true,
      },
      processing: true,
      serverSide: true,
    });

    vespa_tables[source] = datatbl;
  });

  jQuery(document).delegate(
    ".delete-knowledge, .delete-program, .delete-activity, .delete-entity",
    "click",
    function (e) {
      e.preventDefault();
      e.stopPropagation();

      console.log("delete something");

      var modal = jQuery("#confirmBox" + jQuery(this).attr("data-modalid"));

      modal.find("[name=record_id]:first").val(jQuery(this).attr("data-id"));
      modal.modal("show");
    }
  );

  jQuery(document).delegate(".delete-contest-btn", "click", function (e) {
    e.preventDefault();
    e.stopPropagation();

    console.log("delete contest");

    var modal = jQuery("#confirmBox");

    modal.find("[name=record_id]:first").val(jQuery(this).attr("data-id"));
    modal.modal("show");
  });

  jQuery(".mvk-tabs a").click(function (e) {
    if (jQuery(this).attr("href") == "#") {
      e.preventDefault();
    }

    jQuery(".mvk-tabs a.active").removeClass("active");
    jQuery(".mvk-tab-content.active").removeClass("active");

    jQuery(this).addClass("active");
    jQuery(jQuery(this).attr("href")).addClass("active");
  });

  jQuery.datetimepicker.setLocale("hu");

  vespa_init_datepickert();
});

function vespa_init_datepickert() {
  jQuery(".birth-dpicker").datepicker({
    dateFormat: "yy-mm-dd",
    firstDay: 1,
    changeMonth: true,
    changeYear: true,
    yearRange: "-70:+0",
  });

  jQuery(".dtpicker").datetimepicker({
    format: "Y-m-d H:i:00",
  });

  jQuery(".dpicker").datepicker({
    dateFormat: "yy-mm-dd",
    firstDay: 1,
  });
}

function confirmAction(btn) {
  var modal = jQuery(btn).closest(".modal");
  var record_id = modal.find("[name=record_id]:first").val();
  var action = modal.find("[name=action]:first").val();

  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: action,
      id: record_id,
      nonce: vespa_nonce,
    },
    success: function (resp) {
      if (resp.success) {
        if (action != "delete_contest") {
          jQuery(".dataTables_length select").trigger("change");
        } else {
          jQuery("#contest" + record_id).fadeOut();
        }
      }

      modal.modal("hide");
    },
  });
}

function toggleExtraFilters(event, model) {
  event.preventDefault();
  jQuery("#extrafilters").toggle();
  jQuery(`.${model}-extra-filters .row:first`).toggle();
}

function cancelExtraFilters(model) {
  if (model == "institution") {
    jQuery("#ins_type").val("");
    jQuery("#state").val("");
    jQuery("#ins_city").val("");
    jQuery("#ins_name").val("");
    jQuery("#id_number").val("");
    jQuery("#person_data").val("");
  }

  if (model == "athletes") {
    jQuery("#birth_place").val("");
    jQuery("#birth_date").val("");
  }

  jQuery("#extrafilters").toggle();
  jQuery(`.${model}-extra-filters .row:first`).toggle();

  vespa_tables[model].ajax.reload();
}

function doExtraFilters(model) {
  vespa_tables[model].ajax.reload();
}

function exportAthletes() {
  let url = `${vitarex_vespa_ajaxurl_default}?export_${model}`;
  if (model == "institution") {
    url = `${url}&ins_type=${jQuery("#ins_type").val()}`;
    url = `${url}&state=${jQuery("#state").val()}`;
    url = `${url}&ins_city=${jQuery("#ins_city").val().trim()}`;
    url = `${url}&ins_name=${jQuery("#ins_name").val().trim()}`;
    url = `${url}&id_number=${jQuery("#id_number").val().trim()}`;
    url = `${url}&person_data=${jQuery("#person_data").val().trim()}`;
  }

  console.log(url);
  location.href = url;
}

function downloadXLS(model) {
  let url = `${vitarex_vespa_ajaxurl_default}?export_${model}`;

  if (model == "institution") {
    url = `${url}&ins_type=${jQuery("#ins_type").val()}`;
    url = `${url}&state=${jQuery("#state").val()}`;
    url = `${url}&ins_city=${jQuery("#ins_city").val().trim()}`;
    url = `${url}&ins_name=${jQuery("#ins_name").val().trim()}`;
    url = `${url}&id_number=${jQuery("#id_number").val().trim()}`;
    url = `${url}&person_data=${jQuery("#person_data").val().trim()}`;
  }

  if (model == "athletes") {
    url = `${url}&birth_place=${jQuery("#birth_place").val()}`;
    url = `${url}&birth_date=${jQuery("#birth_date").val()}`;
    url = `${url}&school_id=${jQuery("#school_id").val()}`;
  }

  console.log(url);
  location.href = url;

  /*
    fetch(url)
        .then(resp => resp.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            // the filename you want
            a.download = `export_${model}.xls`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
        })

*/
}

function showContestRaceEditor(id) {
  var modal = jQuery("#contest_race_editor");

  jQuery("#record_id").val(id);

  if (id == 0) {
    modal.find(".modal-title").html("Versenyszám hozzáadása");
    jQuery("#sport_id").trigger("change");
  } else {
    var row = jQuery(".contest_races-item[data-id=" + id + "]");

    jQuery("#sport_id").val(row.attr("data-sport_id"));
    jQuery("#sport_id").trigger("change");

    setTimeout(function () {
      jQuery("#event_id").val(row.attr("data-event_id"));
    }, 1000);

    modal.find(".modal-title").html("Versenyszám szerkesztése");
  }

  modal.modal("show");
}

function getSelectedIds(classname, attrname) {
  var ids = [];

  jQuery("." + classname).each(function () {
    if (jQuery(this).prop("checked")) {
      ids.push(jQuery(this).attr(attrname));
    }
  });

  return ids;
}

function saveContestRace() {
  // dfrom, dto, sport_id
  // ch-sport data-id
  // gender
  // ch-disability

  jQuery(".is-invalid").removeClass("is-invalid");
  jQuery(".invalid-feedback").remove();

  var dis = getSelectedIds("ch-disability", "data-id");
  var events = getSelectedIds("ch-sport", "data-id");
  var ag = getSelectedIds("ch-agegroup", "data-id");

  //console.log(dis);
  //console.log(events);
  //console.log(ag);

  var ok = true;
  var fields = ["sport_id", "gender"];

  for (var i = 0; i < fields.length; i++) {
    if (jQuery("#" + fields[i]).val() == "") {
      jQuery("#" + fields[i]).addClass("is-invalid");
      jQuery("#" + fields[i]).after(
        '<div class="invalid-feedback">Kötelező mező</div>'
      );
      ok = false;
    }
  }

  // if (events.length == 0) {
  //   jQuery("#sport_id").after(
  //     '<div class="invalid-feedback">Válassz legalább egy versenyszámot</div>'
  //   );

  //   ok = false;
  // }

  if (dis.length == 0) {
    jQuery("#fogyi").after(
      '<div class="invalid-feedback">Válassz legalább egy fogyatékkossági csoportot</div>'
    );

    ok = false;
  }

  if (ag.length == 0) {
    jQuery("#agh").after(
      '<div class="invalid-feedback">Válassz legalább egy korcsoportot</div>'
    );

    ok = false;
  }

  /*
    if( jQuery('#event_id').val() == null){
        jQuery('#event_id').addClass('is-invalid');
        jQuery('#event_id').after('<div class="invalid-feedback">Kötelező mező</div>');

        return;
    }
    else {
        jQuery('#event_id').removeClass('is-invalid');
    }
*/

  if (!ok) {
    return false;
  }

  var action = "vespa_ajax_table_contest_races_add";
  if (jQuery("#record_id").val() > 0) {
    action = "vespa_ajax_table_contest_races_modify_2";
  }

  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: action,
      id: jQuery("#record_id").val(),
      contest_id: jQuery("#contest_id").val(),
      sport_id: jQuery("#sport_id").val(),
      event_ids: events.join(","),
      disabilities: dis.join(","),
      agegroups: ag.join(","),
      gender: jQuery("#gender").val(),
    },
    success: function (resp) {
      if (resp.success) {
        ajax_table_contest_races_reload();
      }

      //jQuery('#contest_race_editor').modal('hide');
    },
    error: function (xhr, status, err) {
      console.log("Error: ", xhr);
      jQuery("#add_event_feedback").after(
        `<div class="invalid-feedback">${
          xhr.responseJSON?.data?.message ?? err
        }</div>`
      );
    },
  });
}

function filterEvents() {
  var sport_id = jQuery("#sport_id").val();

  jQuery(".ch-sport").each(function () {
    var spid = jQuery(this).attr("data-sport_id");

    if (spid == sport_id) {
      jQuery(this).closest("label").show();
    } else {
      jQuery(this).closest("label").hide();
    }

    jQuery(this).prop("checked", false);
  });
  /*
    var html = '';

    jQuery('#event_tpl > option').each(function(){
        if( jQuery(this).attr('data-sport_id') == sport_id ){
            html += jQuery(this).prop('outerHTML');
        }
    });

    jQuery('#event_id').html(html);
*/
}

function ajax_table_contest_races_reload() {
  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: "vespa_ajax_table_contest_races",
      contest_id: jQuery("#contest_id").val(),
    },
    success: function (resp) {
      if (resp.success) {
        jQuery("#ajax_table_contest_races").replaceWith(resp.html);
      }

      //jQuery('#contest_race_editor').modal('hide');
    },
  });
}

function deleteContestRace(del_id) {
  bootbox.confirm({
    title: "Megerősítés",
    message: "Biztosan törlöd a versenyszámot?",
    buttons: {
      confirm: {
        label: "Igen",
        className: "btn-danger",
      },
      cancel: {
        label: "No",
        className: "btn-default",
      },
    },
    callback: function (result) {
      if (result) {
        jQuery.ajax({
          type: "POST",
          dataType: "json",
          url: vitarex_vespa_ajaxurl,
          data: {
            action: "vespa_ajax_table_contest_races_delete",
            id: del_id,
          },
          success: function (resp) {
            if (resp.success) {
              var row = jQuery(
                ".contest_races-item[data-id=" + del_id + "]"
              ).remove();
            }
          },
        });
      }
    },
  });
}

function finalizeContest() {
  bootbox.dialog({
    title: "Megerősítés",
    message: "Biztosan véglegesíted a versenyt?",
    buttons: {
      cancel: {
        label: "Nem",
        className: "btn-default",
      },
      confirm: {
        label: "Igen",
        className: "btn-danger",
        callback: function () {  
          jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: vitarex_vespa_ajaxurl,
            data: {
              action: "vespa_ajax_table_contest_races_finalize",
              contest_id: jQuery("#contest_id").val(),
            },
            success: function (resp) {
              if (resp.success) {
                location.href = resp.url;
              }
            },
          }); 
        },
      },
      confirmWithEmail: {
        label: "Igen, értesítés küldése nevezetteknek",
        className: "btn-warning",
        callback: function () {
          let dialog = bootbox.dialog({
            message: '<p class="text-center mb-0"><i class="fas fa-spin fa-cog"></i> Email küldés folyamatban...</p>',
            closeButton: false
          });
          jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: vitarex_vespa_ajaxurl,
            data: {
              action: "vespa_ajax_table_contest_races_finalize",
              contest_id: jQuery("#contest_id").val(),
              emails: 1
            },
            success: function (resp) {
              if (resp.success) {
                location.href = resp.url;
              }
            },
            error: function (resp) {
              bootbox.alert(resp.responseJSON.data.message);
            },
            complete: function () {
              dialog.modal('hide');
            }
          }); 
        },
      },
    },
    
  });
}

function showContestEscortEditor(id) {
  var modal = jQuery("#contest_escort_editor");

  jQuery("#record_id").val(id);

  jQuery("#institution_id").trigger("change");
  jQuery("#escort_name").val("");
  jQuery("#escort_email").val("");
  jQuery("#escort_phone").val("");

  if (id == 0) {
    modal.find(".modal-title").html("Kísérő hozzáadása");
  } else {
    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: vitarex_vespa_ajaxurl,
      data: {
        action: "vespa_ajax_table_contest_escorts_get",
        id: id,
      },
      success: function (resp) {
        if (resp.data) {
          jQuery("#institution_id").val(resp.data.school_id);
          jQuery("#institution_id").trigger("change");
          jQuery("#escort_name").val(resp.data.escort_name);
          jQuery("#escort_email").val(resp.data.escort_email);
          jQuery("#escort_phone").val(resp.data.escort_phone);
        } else {
          jQuery("#contest_escort_editor").modal("hide");
        }
      },
    });

    modal.find(".modal-title").html("Kísérő szerkesztése");
  }

  modal.modal("show");
}

function setRequiredField(field) {
  const val = jQuery(field).val();
  if (val == null || val?.trim() == "") {
    jQuery(field).addClass("is-invalid");
    jQuery(field).after('<div class="invalid-feedback">Kötelező mező</div>');
    return true;
  } else {
    jQuery(field).removeClass("is-invalid");
    return false;
  }
}

const validateEmail = (email) => {
  return email.match(
    /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
  );
};

function saveContestEscort() {
  if (setRequiredField("#escort_name")) return;

  const email = jQuery("#escort_email").val();

  if (email != "") {
    if (validateEmail(email)) {
      jQuery("#escort_email").removeClass("is-invalid");
    } else {
      jQuery("#escort_email").addClass("is-invalid");
      jQuery("#escort_email").after(
        '<div class="invalid-feedback">Érvénytelen email cím</div>'
      );
      return;
    }
  }

  var action = "vespa_ajax_table_contest_escorts_add";
  if (jQuery("#record_id").val() > 0) {
    action = "vespa_ajax_table_contest_escorts_modify";
  }

  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: action,
      contest_id: jQuery("#contest_id").val(),
      contest_escort_id: jQuery("#record_id").val(),
      school_id: jQuery("#institution_id").val(),
      escort_name: jQuery("#escort_name").val(),
      escort_email: jQuery("#escort_email").val(),
      escort_phone: jQuery("#escort_phone").val(),
    },
    success: function (resp) {
      if (resp.success) {
        ajax_table_contest_escorts_reload();
      }

      jQuery("#contest_escort_editor").modal("hide");
    },
  });
}

function ajax_table_contest_escorts_reload() {
  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: "vespa_ajax_table_contest_escorts",
      contest_id: jQuery("#contest_id").val(),
    },
    success: function (resp) {
      if (resp.success) {
        jQuery("#ajax_table_contest_escorts").replaceWith(resp.html);
      }

      jQuery("#contest_escort_editor").modal("hide");
    },
  });
}

function deleteContestEscort(del_id) {
  bootbox.confirm({
    title: "Megerősítés",
    message: "Biztosan törlöd a kísérőt?",
    buttons: {
      confirm: {
        label: "Igen",
        className: "btn-danger",
      },
      cancel: {
        label: "No",
        className: "btn-default",
      },
    },
    callback: function (result) {
      if (result) {
        jQuery.ajax({
          type: "POST",
          dataType: "json",
          url: vitarex_vespa_ajaxurl,
          data: {
            action: "vespa_ajax_table_contest_escorts_delete",
            id: del_id,
          },
          success: function (resp) {
            if (resp.success) {
              jQuery(".contest_escorts-item[data-id=" + del_id + "]").remove();
            }
          },
        });
      }
    },
  });
}

function vespa_school_entry(id) {
  bootbox.confirm({
    title: "Megerősítés",
    message: "Biztosan benevezed az iskolád erre a versenyre?",
    buttons: {
      confirm: {
        label: "Igen",
        className: "btn-danger",
      },
      cancel: {
        label: "No",
        className: "btn-default",
      },
    },
    callback: function (result) {
      if (result) {
        jQuery.ajax({
          type: "POST",
          dataType: "json",
          url: vitarex_vespa_ajaxurl,
          data: {
            action: "school_signup",
            contest_id: id,
            school_id: 0,
          },
          success: function (resp) {
            if (resp.success) {
              ajax_table_contest_entries_reload();
            }
          },
        });
      }
    },
  });
}

function ajax_table_contest_entries_reload() {
  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: "vespa_ajax_table_contest_entries",
      contest_id: jQuery("#contest_id").val(),
    },
    success: function (resp) {
      if (resp.success) {
        jQuery("#ajax_table_contest_entries").replaceWith(resp.html);
      }
    },
  });
}

// TODO: event filtering?
function vespa_contest_entry_modal(event_id) {
  var modal = jQuery("#contest_entry_modal");

  jQuery("#entry_event_id").val(event_id);
  modal.modal("show");
}

function vespa_entry_athletes() {
  var ids = [];

  jQuery(".athlete-ch:checked").each(function () {
    ids.push(jQuery(this).attr("data-id"));
  });

  console.log(ids);

  if (ids.length == 0) {
    bootbox.alert("Nincs kiválasztva versenyző!");
    return false;
  }

  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: "athletes_signup",
      contest_id: jQuery("#contest_id").val(),
      event_id: jQuery("#entry_event_id").val(),
      athlete_ids: ids.join(","),
      school_id: 0,
    },
    success: function (resp) {
      if (resp.success) {
        ajax_table_contest_entries_reload();

        jQuery("#contest_entry_modal").modal("hide");
      }
    },
  });
}

function storeContestEntering() {
  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: "school_signup",
      contest_id: jQuery("#contest_id").val(),
      school_id: 0,
    },
    success: function (resp) {
      if (resp.success) {
        //ajax_table_contest_entries_reload();
        jQuery(".nyilatkozom-p").after("<p>" + resp.message + "</p>");
      }
    },
  });
}

function editEscorts(btn) {
  jQuery(btn).hide();

  jQuery("#vespa-escorts-form").show();
}

function showEntered(btn, e) {
  e.preventDefault();
  jQuery(btn).closest("tr").next("tr").next(".tovabbjutok").hide();
  jQuery(btn).closest("tr").next(".entered-athletes").toggle();
}

function tovabbjutas(btn, e) {
  e.preventDefault();
  jQuery(btn).closest("tr").next(".entered-athletes").hide();
  jQuery(btn).closest("tr").next("tr").next(".tovabbjutok").toggle();
}

function reopenContest(event, caller = "nincs") {
  event.preventDefault();

  jQuery.ajax({
    type: "POST",
    dataType: "json",
    url: vitarex_vespa_ajaxurl,
    data: {
      action: "reopen_contest",
      contest_id: jQuery("#contest_id").val(),
      caller: caller,
    },
    success: function (resp) {
      if (resp.success) {
        location.reload()
      }
    },
  });
}

function tabKivalasztas(evt, tabId) {
  var i, x, tablinks;
  x = document.getElementsByClassName("w3-tabarea");
  for (i = 0; i < x.length; i++) {
    x[i].style.display = "none";
  }

  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < x.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" w3-red", "");
  }
  var tab = document.getElementById(tabId);
  if (tab) tab.style.display = "block";
  evt.currentTarget.className += " w3-red";

  document.getElementById("selected_tab").value = tabId;
}

function addTeacherRow() {
  var rows = jQuery("#teacher_rows");
  var html =
    '<div class="row kisero-row teacher-row">' +
    '  <div class="col-md-2"><input type="text" class="form-control" name="teacher_teljes_nev[]"></div>' +
    '  <div class="col-md-2"><input type="text" class="form-control" name="teacher_mobil[]"></div>' +
    '  <div class="col-md-2"><input type="text" class="form-control" name="teacher_email[]"></div>' +
    '  <div class="col-md-2"><input type="text" class="form-control" name="teacher_szuletesi_hely[]"></div>' +
    '  <div class="col-md-2"><input type="date" class="form-control" name="teacher_szuletesi_ido[]"></div>' +
    '  <div class="col-md-1"><input type="text" class="form-control" name="teacher_iskola_neve[]"></div>' +
    '  <div class="col-md-1"><button type="button" class="button btn-remove-teacher" onclick="removeTeacherRow(this);">✕</button></div>' +
    "</div>";
  rows.append(html);
}

function removeTeacherRow(btn) {
  var rows = jQuery("#teacher_rows");
  // ne lehessen az utolsó sort is törölni – maradjon legalább egy üres sor
  if (rows.find(".teacher-row").length <= 1) {
    jQuery(btn).closest(".teacher-row").find("input").val("");
    return;
  }
  jQuery(btn).closest(".teacher-row").remove();
}
