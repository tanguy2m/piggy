// DataTables migrated to 1.10: https://datatables.net/upgrade/1.10-convert
var transacsDT;
var patternsDT;
$.extend( true, $.fn.dataTable.defaults, {
  "language": {
    "url": "https://cdn.datatables.net/plug-ins/1.10.16/i18n/French.json"
  },
  // Enable or disable the display of a 'processing' indicator when the table is being processed
  "processing": true,
  // Server-side processing mode
  "serverSide": true
});

// Récupération de la liste des catégories
var $ref_select = $('<select class="form-control input-sm"><option value="0"></option></select>');
var getCategories = $.get('ws.php/categories', function (data) {
  for (id in data) {
    $('<option></option>')
      .prop('value', id)
      .text(data[id])
      .appendTo($ref_select); // Remplissage du select de référence
  }
}, "json");

$.widget( "custom.catFilter", {

  options: {
    catColID: 0,
    initID: 0
  },

  _create: function() {
    var self = this;
    this.$select = $ref_select.clone(true)
      .on('change',function(){
        self.filter($(this).val(),true);
      })
      .on('blur',function(){
        $(this).val("-1"); // Specific iPad: http://www.citytechinc.com/us/en/blog/2014/02/resetting-the-value-of-select-boxes-in-safari-for-ios.html
      })
      .find('option[value=0]').text('Aucune catégorie').parent()
      .prepend('<option value="-1"></option>')
      .appendTo(this.element);
    this.$label = $('<span class="margin-right hidden"></span>')
      .appendTo(this.element);
    this.$button = $('<button type="button" class="btn btn-danger btn-xs hidden"><span class="glyphicon glyphicon-remove"></span></button>')
      .on("click",$.proxy(this.unfilter,this))
      .appendTo(this.element);
    if (this.options.initID)
      this.filter(this.options.initID,false);
  },

  filter: function(catID,apply) {
    this.$label
      .html(this.$select.find('[value="'+catID+'"]').text())
      .removeClass("hidden");
    this.$button.removeClass("hidden");
    this.$select.addClass("hidden");
    if (apply)
      this.$select.closest("table").DataTable()
        .columns(this.options.catColID).search(catID);
    this.$select.val("-1");
  },

  unfilter: function() {
    this.$label.addClass("hidden");
    this.$button.addClass("hidden");
    this.$select.removeClass("hidden");
    this.$select.closest("table").DataTable()
      .columns(this.options.catColID).search('');
  }
});

//Remplissage d'une cellule de sélection de la catégorie
$.extend($.fn, {
  addCatSelect: function (catID,table,transacID){
    $newSelect = $ref_select.clone(true)
      .on('change',function(){
        apiCall("PUT","transactions/"+transacID,{
          cat_id: $(this).val()
        });
      })
      .val(catID);
    $(this).append($newSelect);
    return this;
  }
});

$(document).ready(function () {

  // Récupération des paramètres de la page
  var params = {}
  var prmstr = window.location.search.substr(1);
  if (prmstr != null && prmstr != "") {
    var prmarr = prmstr.split("&");
    for ( var i = 0; i < prmarr.length; i++) {
      var tmparr = prmarr[i].split("=");
      params[tmparr[0]] = tmparr[1];
    }
  }
  var rangeRequested = ("debut" in params && "fin" in params);

  // Création de tous les widgets de sélection des catégories
  getCategories.then(function(){
    $('#patterns tfoot :nth-child(3)').prepend($ref_select.clone(true)); // Formulaire d'ajout de pattern
    $('#transactions thead tr:last th:last').catFilter({ // Tableau des transactions
      catColID: 4,
      initID: "catID" in params ? params["catID"] : 0 // Sélection de la catégorie si spécifiée dans l'url
    });
    $('#patterns thead tr:last th:last').catFilter({catColID: 2}); // Tableau des patterns
    $ref_select.clone(true) // Formulaire d'ajout de prélèvement
      .attr('id','prelevCat')
      .appendTo("#withdra_cat");
  });

  // Duplication de widgets entre les 2 tableaux
  $('#patterns [data-widget=textFilter]')
    .append($('#transactions [data-widget=textFilter]').html());

  // Remplissage de la pop-up de configuration à l'ouverture
  $('#settings').on('show.bs.modal', function() {
    if ( ! $.fn.dataTable.isDataTable($('#patterns')[0]) ) {
      patternsDT = $('#patterns').DataTable({
        "dom": '<"absolute"r>t<"pull-left"i><"pull-right"p><"clearfix">',
        "pageLength": 5,
        "columnDefs": [
          // Category column
          {
            "targets": [2], "width": "200px",
            "createdCell": function(cell, cellData, rowData, rowIndex, colIndex ) {
              $(cell).html('')
                .addCatSelect(cellData,'patterns',rowData[0]);
            }
          },
          // ID column (delete button)
          {
            "targets": [0], "width": "1px", "orderable": false,
            "createdCell": function(cell, cellData, rowData, rowIndex, colIndex ) {
              $(cell).html('');
              var that = this;
              $('#deletePattern').clone().removeClass("hidden")
                .on('click',function(){
                  $.ajax("ws.php/patterns/" +rowData[0],{
                    type: "DELETE"
                  }).done(function() {that.draw(true);});
                })
                .appendTo(cell);
            }
          }
        ],
        "autoWidth": false,
        "orderCellsTop": true,
        "order": [[0, "desc"]], // ID column
        "ajax": "ws.php/patterns"
      });
    }
  });

  // Ajout d'un pattern
  $('#savePattern').on('click', function(e) {
    apiCall("POST","patterns",{
      cat_id: $('#patterns tfoot select').val(),
      pattern: $('#patterns tfoot input').val()
    }).done(function() {patternsDT.draw(true);});
    $('#patterns tfoot select option:selected').removeAttr('selected');
    $('#patterns tfoot input').val('');
  });

  // Recherche textuelle sur toute une dataTable
  $('body').on('keyup', '[data-widget=keywordSearch]', function(e) {
    dt = $(this).closest("table").DataTable();
    if ($(this).val() != dt.search()) // Nouvelle requête uniquement si val différente
      dt.search($(this).val());
    if ($(this).val() != '')
      $(this).parent().find("[data-widget=clearTextFilter]").removeClass("hidden");
    else
      $(this).parent().find("[data-widget=clearTextFilter]").addClass("hidden");
  });
  $("[data-widget=clearTextFilter]").on('click',function(e) {
    $(this).addClass("hidden")
      .closest('[data-widget=textFilter]').find('[data-widget=keywordSearch]').val('');
    $(this).closest("table").DataTable().search('');
  });

  // Sauvegarde du commentaire
  $("body").on('keyup', '[data-widget=commentInput]', function(e) {
    dt = $(this).closest("table").DataTable();
    val = $(this).val();
    if (val != $(this).data('oldValue')) {
      $(this).data('oldValue',val);
      rowData = dt.row($(this).closest('tr')).data();
      apiCall("PUT","transactions/" + rowData[0],{
        comment: val
      });
    }
  });

  // Création du widget de sélection de la période
  // http://www.dangrossman.info/2012/08/20/a-date-range-picker-for-twitter-bootstrap/
  $('[data-toggle=dateRangePicker]').daterangepicker(
    {
      ranges: {
        '7 derniers jours': [moment().subtract(6, 'days'), moment()],
        '30 derniers jours': [moment().subtract(30, 'days'), moment()],
        'Mois en cours': [moment().startOf('month'), moment().endOf('month')],
        'Mois précédent': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
      },
      startDate: rangeRequested ? moment(params.debut) : moment().subtract(30, 'days'),
      endDate: rangeRequested ? moment(params.fin) : moment(),
      format: 'DD MMM YY',
      buttonClasses: ['btn', 'btn-sm'],
      showDropdowns: true,
      locale: {
        applyLabel: 'Appliquer',
        cancelLabel: 'Annuler',
        fromLabel: 'Du',
        toLabel: 'Au',
        weekLabel: 'W',
        customRangeLabel: 'Autre période',
        firstDay: 1
      }
    },
    function (start, end) {
      $('#dateRange').text(start.format('DD MMM YY') + ' - ' + end.format('DD MMM YY'));
      $("#clearDateFilter").removeClass("hidden");
      transacsDT.draw(true); // Rechargement du tableau
    }
  );
  // Affichage du bon label
  $('#dateRange').text(rangeRequested ? moment(params.debut).format('DD MMM YY') + ' - ' + moment(params.fin).format('DD MMM YY') : '30 derniers jours');
  // Suppression du filtre sur la date
  $('#clearDateFilter').on('click',function(e){
    $(this).addClass("hidden");
    $('#dateRange').text('');
    transacsDT.draw(true); // Redraw the table
  });

  // Gestion de l'affichage des champs commentaires
  $("#transactions").on("click","[data-toggle=commentField]",function (e) {
    $(this).siblings().addBack()
      .toggleClass('hidden');
  });

  // Build DataTable, migrated to 1.10
  transacsDT = $('#transactions').DataTable({
    // Define the table control elements to appear on the page and in what order
    "dom":
      "<'row'<'col-sm-12'tr>>" +
      "<'row'<'col-sm-3'l><'col-sm-4'i><'col-sm-5'p>>",
    // Change the initial page length (number of rows per page)
    "pageLength": 25,
    // Set column definition initialisation properties
    "columnDefs": [
      // ID & Comment columns
      { "targets": [0,5], "visible": false },
      // Date & Amount columns
      { "targets": [1,3,4], "className": "centered" },
      // Date column
      { 
        "targets": [1], "type": "date", "width": "220px",
        "createdCell": function (cell, cellData, rowData, rowIndex, colIndex ) {
          $(cell).text(moment(cellData).format('DD MMM YYYY'));
        }
      },
      // Label + Comment column
      {
        "targets": [2],
        "createdCell": function (cell, cellData, rowData, rowIndex, colIndex ) {
          $('<span data-toggle="commentField" class="glyphicon glyphicon-chevron-down pull-right"></span>')
            .appendTo(cell);
          $('<span data-toggle="commentField" class="glyphicon glyphicon-chevron-up pull-right hidden"></span>')
            .appendTo(cell);
          $('<input type="text" data-widget="commentInput" class="form-control input-sm hidden">')
            .val(rowData[5])
            .data('oldValue',rowData[5])
            .appendTo(cell);
          if ( rowData[5] )
            $(cell).children().toggleClass("hidden");
        }
      },
      // Amount column
      {
        "targets": [3], "width": "50px",
        "createdCell": function (cell, cellData, rowData, rowIndex, colIndex ) {
          $(cell).append('€');
        }
      },
      // Category column
      {
        "targets": [4], "width": "20%", "searchable": true,
        "createdCell": function(cell, cellData, rowData, rowIndex, colIndex ) {
          $(cell).html('');
          getCategories.done(function(){
            $(cell).addCatSelect(cellData,'transactions',rowData[0]);
          });
        }
      }
    ],
    // Define an initial search for individual columns
    "searchCols": [
      null, null, null, null,
      "catID" in params ? {"search": params["catID"]} : null, // Filter on category if asked by URL
      null
    ],
    // Control which cell the order event handler will be applied to in a column
    "orderCellsTop": true,
    // Initial order (sort) to apply to the table
    "order": [[1, "desc"]], // Date column
    // Load data for the table's content from an Ajax source
    "ajax": {
      "url": "ws.php/transactions",
      "data": function(d) {
        if ($('#dateRange').text() != "") {
          d.start_date = $('[data-toggle=dateRangePicker]').data('daterangepicker').startDate.format("YYYY-MM-D");
          d.end_date = $('[data-toggle=dateRangePicker]').data('daterangepicker').endDate.format("YYYY-MM-D");
        }  
      }
    }
  });

  // =================================
  //   Gestion des fichiers QIF/OFX
  // =================================

  // Gestion de l'ouverture de la fenêtre de sélection d'un fichier
  $('[href="#uploadFile"]').on('click', function () {
    $('#fileInput').click();
    return false;
  });

  // Gestion de l'upload de fichiers qif/ofx
  $("#fileInput").on("change", function (e) {
    var file = this.files[0];
    $(this).val("");
    if (!file.name.match("\.(ofx|qif)$", "i")) {
      notification(false,"Fichier local: " + file.name,"Format de fichier non supporté");
      return;
    }

    var formData = new FormData();
    formData.append("exportFile", file);
    $.ajax('ws.php/transactions',{
      type: "POST",
      processData: false,
      contentType: false,
      data: formData
    }).done( function(data) { //Errors are already caught
      if(typeof(data) === "string") { // Answer's content-type is not JSON => error
        notification(false,"Erreur lors du traitement du fichier local: " + file.name,data);
        return;
      }
      notification(true,"Fichier local: " + file.name,data);
      transacsDT.draw(true); // Reload the transaction table
    });
  });

  // =================================
  //  Formulaire ajout de prélèvement
  // =================================

  // Affichage du panel
  $('[href="#addPrelev"]').on("click",function(e){
    $("#prelevPanel").removeClass("hidden");
    e.preventDefault();
  });
  // Disparition du panel
  $("#closePanel").on("click",function() {$("#prelevPanel").addClass("hidden");} );

  // Mise à jour des headers si changement du premier mois
  $('#firstMonth').on('change', function (){
    var date = moment($("#firstMonth").val(),"YYYY-MM");
    $.each($("#mensualites > thead > tr > td"),function(i){
      $(this).data("value",date.endOf('month').format("YYYY-MM-DD"));
      if (i>0)
        $(this).text(date.format("MMM YYYY"));
      date.add(1, "months");
    });
  });

  // Premier prélèvement positionné par défaut au mois actuel
  $("#firstMonth").val(moment().format("YYYY-MM")).trigger('change');

  // Envoi du prélèvement au serveur
  $("#sendPrelev").on('click',function(e){
    // Création du tableau des mensualités
    var mensualites = [];
    var total = 0;
    $.each($("#mensualites > tbody input"),function(i){
      if ($(this).val()) {
        var mensualite = {};
        mensualite.date = $("#mensualites > thead > tr > td:nth-child("+(i+1)+")").data("value");
        mensualite.montant = $(this).val().replace(",", ".");
        total += parseFloat(mensualite.montant);
        mensualites.push(mensualite);
      }
    });
    // Envoi au serveur
    apiCall("POST","withdrawals",{
      comment: $("#description").val(),
      cat_id: $("#prelevCat").val(),
      total: total,
      mensualites: mensualites
    }).done(function(data){
      // Remise à zéro du formulaire
      $("#prelevPanel input[type=text]").val("");
      $("#prelevCat").val("0");
      $("#firstMonth").val(moment().format("YYYY-MM")).trigger('change');
      // Disparition du panel
      $("#prelevPanel").addClass("hidden");
      // Mise à jour du tableau des transactions
      transacsDT.draw(true);
      // Notification de succès
      notification(true,"Ajout d'un prélèvement",data);
    });
  });
});