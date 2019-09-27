var getYears;

$.widget( "custom.synthesisTable", {

  // Default options.
  options: {
    header: "",
    hideEmptyRows: false,
    displayHyperlinks: false,
    showDelete: false,
    deleteDOM: '<button type="button" class="btn btn-danger btn-xs pull-right"><span class="glyphicon glyphicon-remove"></span></button>'
  },

  _create: function() {
    var that = this;
    // Récupération des click sur le bouton delete
    this._on( this.element, {
      "click .deleteWidget": function (event) {
        $tr = $(event.currentTarget).closest('tr');
        var id = $tr.attr('id');
        $tr.remove();
        this._trigger( "onDelete", event, {
          id: id
        });
      }
    });
    // Remplissage du thead
    this.element.find("> thead").append("<tr><th>"+this.options.header+"</th></tr>");
    // Remplissage du tbody
    if(this.options.hideEmptyRows)
      this.element.find("> tbody").append('<tr id="0"><th>Aucune donnée disponible sur la période</th></tr>');
    $.get('ws.php/'+that.element.attr('id'), function (data) {
      // Affichage des noms dans la première colonne
      for (id in data) {
        $tr = $('<tr id="' + id + '"><th>' + data[id] + '</th></tr>');
        if(that.options.showDelete)
          $tr.find("> th").append($(that.options.deleteDOM).addClass("deleteWidget")); // Ajout du bouton suppr si nécessaire
        $tr.appendTo(that.element.find("> tbody"));
      }
      // Remplissage des colonnes
      getYears.then(function(){
        that.update();
      });
    });
    // Remplissge du tfoot
    this.element.find("> tfoot").append("<tr><th>TOTAL</th></tr>");
  },

  update: function (){
    var that = this;
    //Remise à zéro du tableau
    that.element.find("thead tr th:gt(0)").remove(); // Suppression des labels des mois
    that.element.find("td").remove(); // Suppression de toutes les cellules spécifiques à un mois

    $.get("ws.php/synthesis/"+that.element.attr('id'), {
      debut: $("#yearsSelect").val() + '-01',
      fin: $("#yearsSelect").val() + '-12'
    }, function (result) {
      for (var month in result.data) {
        // Affichage du mois dans le header
        that.element.find("> thead > tr")
          .append("<th>" + moment(month).format("MMM") + "</th>");

        // Ajout de toutes les cellules
        that.element.find("> tbody > tr")
          .append("<td></td>");

        // Affichage des totaux par catégorie
        var total = 0;
        $(result.data[month]).each(function (i, mensualite) {
          var value = mensualite[1] + " €";
          var url = "index.html?debut=" + moment(month).startOf('month').format("YYYY-MM-DD") + "&fin=" + moment(month).endOf('month').format("YYYY-MM-DD") + "&catID=" + mensualite[0];
          that.element.find("> tbody > tr#" + mensualite[0] + " > td:last") //Rien ne se passe pour la catégorie null
            .append(that.options.displayHyperlinks ? $('<a href="'+url+'"></a>').append(value) : value);
          if (result.excluded_cats.indexOf(mensualite[0]) == -1)
            total += mensualite[1]; // Mais le total est mis à jour => nickel
        });

        // Affichage du grand total
        that.element.find("> tfoot > tr ")
          .append("<td>" + parseFloat(total.toPrecision(12)) + " €</td>"); // Précision des flottants en JS: http://stackoverflow.com/questions/588004/is-floating-point-math-broken
      }

      // Masquage des lignes vides si nécessaire
      if(that.options.hideEmptyRows) {
        that.element.find("tr#0").show();
        $.each( that.element.find("> tbody > tr:gt(0)"), function(i,tr){
          if ($(tr).find(">td").text() == "") {
            $(tr).hide();
          } else {
            $(tr).show();
            that.element.find("tr#0").hide();
          }
        });
      }
    });
  }

});

$(document).ready(function () {

  // Gestion de la dynamique des tabs
  $("[role=tablist] a").on('click',function(e){
    e.preventDefault();
    // Mise à jour du widget tabs
    $li = $(this).parent();
    $li.siblings().addBack().toggleClass("active");
    // Affichage de la bonne table
    $("table").toggleClass("hidden");
    // Mise à jour de la table
    $("table:not(.hidden)").synthesisTable("update");
  });

  // Récupération de la liste des années présentes dans la base
  getYears = $.get('ws.php/years', function (data) {
    $(data).each(function (i, year) {
      $('#yearsSelect').append('<option value="' + year + '">' + year + '</option>');
    });
    $("#yearsSelect option[value='" + new Date().getFullYear() + "']").attr('selected', 'selected');
  });

  // Chargement des tableaux
  $("#categories").synthesisTable({
    displayHyperlinks: true
  });
  $("#withdrawals").synthesisTable({
    header: "Descriptif",
    hideEmptyRows: true,
    showDelete: true,
    onDelete: function (event,data) {
      $.ajax("ws.php/withdrawals/"+data.id, {
        type: "DELETE"
      }, function() { $("#withdrawals").synthesisTable("update"); } );
    }
  });

  // Rechargement de la table affichée si la date change
  $('#yearsSelect').change(function () {
    $("table:not(.hidden)").synthesisTable("update");
  });
});