jQuery(document).ready(function ($) {
  const searchForm = $("#lexhoy-despachos-search-form");
  const resultsContainer = $("#lexhoy-despachos-results");

  searchForm.on("submit", function (e) {
    e.preventDefault();

    const searchData = {
      action: "lexhoy_despachos_search",
      nonce: lexhoyDespachosData.nonce,
      search: $("#search").val(),
      provincia: $("#provincia").val(),
      area: $("#area").val(),
    };

    $.ajax({
      url: lexhoyDespachosData.ajaxurl,
      type: "POST",
      data: searchData,
      beforeSend: function () {
        resultsContainer.html(
          '<div class="loading">Buscando despachos...</div>'
        );
      },
      success: function (response) {
        if (response.success) {
          displayResults(response.data);
        } else {
          resultsContainer.html(
            '<div class="error">Error en la búsqueda: ' +
              response.data +
              "</div>"
          );
        }
      },
      error: function () {
        resultsContainer.html(
          '<div class="error">Error al conectar con el servidor</div>'
        );
      },
    });
  });

  function displayResults(results) {
    if (!results.length) {
      resultsContainer.html(
        '<div class="no-results">No se encontraron despachos</div>'
      );
      return;
    }

    let html = '<div class="despachos-grid">';
    results.forEach(function (despacho) {
      html += `
                <div class="despacho-card">
                    <h3>${despacho.nombre}</h3>
                    <p class="direccion">${despacho.direccion}</p>
                    <p class="localidad">${despacho.localidad}, ${
        despacho.provincia
      }</p>
                    <p class="areas">${despacho.areas.join(", ")}</p>
                    <a href="${despacho.link}" class="ver-mas">Ver más</a>
                </div>
            `;
    });
    html += "</div>";

    resultsContainer.html(html);
  }
});

// Función para inicializar la búsqueda
function initializeSearch() {
  try {
    console.log("Inicializando búsqueda...");
    const searchClient = algoliasearch(
      lexhoyDespachosData.appId,
      lexhoyDespachosData.searchApiKey
    );

    const search = instantsearch({
      indexName: lexhoyDespachosData.indexName,
      searchClient,
      routing: true,
    });

    // Widget de búsqueda
    search.addWidgets([
      instantsearch.widgets.searchBox({
        container: "#searchbox",
        placeholder: "Buscar...",
        showReset: false,
        showSubmit: true,
        submitTitle: "Buscar",
        resetTitle: "Limpiar",
      }),
    ]);

    // Widget de refinamientos activos
    search.addWidgets([
      instantsearch.widgets.currentRefinements({
        container: "#current-refinements",
      }),
    ]);

    // Widget de refinamientos por provincia
    search.addWidgets([
      instantsearch.widgets.refinementList({
        container: "#province-list",
        attribute: "provincia",
        searchable: true,
        searchablePlaceholder: "Buscar provincia...",
        limit: 10,
        showMore: true,
        showMoreLimit: 20,
        templates: {
          showMoreText: "Ver más",
          showLessText: "Ver menos",
        },
      }),
    ]);

    // Widget de refinamientos por localidad
    search.addWidgets([
      instantsearch.widgets.refinementList({
        container: "#location-list",
        attribute: "localidad",
        searchable: true,
        searchablePlaceholder: "Buscar localidad...",
        limit: 10,
        showMore: true,
        showMoreLimit: 20,
        templates: {
          showMoreText: "Ver más",
          showLessText: "Ver menos",
        },
      }),
    ]);

    // Widget de refinamientos por área de práctica
    search.addWidgets([
      instantsearch.widgets.refinementList({
        container: "#practice-list",
        attribute: "areas_practica",
        searchable: true,
        searchablePlaceholder: "Buscar área...",
        limit: 10,
        showMore: true,
        showMoreLimit: 20,
        templates: {
          showMoreText: "Ver más",
          showLessText: "Ver menos",
        },
      }),
    ]);

    // Widget de resultados
    search.addWidgets([
      instantsearch.widgets.hits({
        container: "#hits",
        templates: {
          item: document.getElementById("hit-template").innerHTML,
          empty: document.getElementById("no-results-template").innerHTML,
        },
      }),
    ]);

    // Widget de paginación
    search.addWidgets([
      instantsearch.widgets.pagination({
        container: "#pagination",
        padding: 2,
        showFirst: false,
        showLast: false,
      }),
    ]);

    // Iniciar la búsqueda
    search.start();

    // Manejar clics en letras del alfabeto
    document.querySelectorAll(".alphabet-letter").forEach((letter) => {
      letter.addEventListener("click", () => {
        const searchInput = document.querySelector(".ais-SearchBox-input");
        if (searchInput) {
          searchInput.value = letter.textContent;
          searchInput.dispatchEvent(new Event("input"));
        }
      });
    });

    // Manejar clics en pestañas de filtros
    document.querySelectorAll(".filter-tab-btn").forEach((button) => {
      button.addEventListener("click", () => {
        // Remover clase active de todos los botones y paneles
        document
          .querySelectorAll(".filter-tab-btn")
          .forEach((btn) => btn.classList.remove("active"));
        document
          .querySelectorAll(".filter-tab-pane")
          .forEach((pane) => pane.classList.remove("active"));

        // Agregar clase active al botón clickeado
        button.classList.add("active");

        // Mostrar el panel correspondiente
        const tabId = button.getAttribute("data-tab");
        document.getElementById(`${tabId}-list`).classList.add("active");
      });
    });
  } catch (error) {
    console.error("Error al inicializar la búsqueda:", error);
    showNotification(
      "error",
      "Error al inicializar la búsqueda. Por favor, recarga la página."
    );
  }
}

// Función para mostrar notificaciones
function showNotification(type, message) {
  const notification = document.createElement("div");
  notification.className = `despacho-notification ${type}`;
  notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${
              type === "success" ? "fa-check-circle" : "fa-exclamation-circle"
            }"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.classList.add("show");
  }, 100);

  const closeButton = notification.querySelector(".notification-close");
  closeButton.addEventListener("click", () => {
    notification.classList.remove("show");
    setTimeout(() => {
      notification.remove();
    }, 300);
  });

  setTimeout(() => {
    if (notification.classList.contains("show")) {
      notification.classList.remove("show");
      setTimeout(() => {
        notification.remove();
      }, 300);
    }
  }, 5000);
}

// Función para navegar a la página del despacho
window.navigateToDespacho = function (slug) {
  window.location.href = `/despacho/${slug}`;
};

// Inicializar cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", initializeSearch);
