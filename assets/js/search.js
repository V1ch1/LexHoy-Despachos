// Variable global para la página actual
var currentPage = 1;

jQuery(document).ready(function ($) {
  let currentFilters = {
    search: "",
    provincia: [],
    localidad: [],
    area: [],
  };
  let isLoading = false;
  let searchTimeout;

  // Inicializar la búsqueda
  initializeSearch();

  function initializeSearch() {
    // Cargar despachos iniciales
    loadDespachos();

    // Event listeners
    setupEventListeners();
  }

  function setupEventListeners() {
    // Verificar que el input existe
    if ($("#searchbox").length === 0) {
      return;
    }

    // Búsqueda por texto con debounce optimizado
    $("#searchbox").on("input", function () {
      clearTimeout(searchTimeout);
      const searchTerm = $(this).val();

      searchTimeout = setTimeout(function () {
        currentFilters.search = searchTerm;
        currentPage = 1;
        loadDespachos();
      }, 300); // Reducido de 500ms a 300ms
    });

    // Botón de búsqueda
    $("#search-button").on("click", function () {
      if (isLoading) {
        return; // Evitar múltiples clics
      }
      currentFilters.search = $("#searchbox").val();
      currentPage = 1;
      loadDespachos();
    });

    // Enter en el campo de búsqueda
    $("#searchbox").on("keypress", function (e) {
      if (e.which === 13 && !isLoading) {
        currentFilters.search = $(this).val();
        currentPage = 1;
        loadDespachos();
      }
    });

    // Filtros por checkbox con optimización
    $(".filter-checkbox").on("change", function () {
      if (isLoading) return; // Evitar cambios durante carga

      const filterType = $(this).data("filter");
      const filterValue = $(this).val();
      const isChecked = $(this).is(":checked");

      if (isChecked) {
        if (!currentFilters[filterType].includes(filterValue)) {
          currentFilters[filterType].push(filterValue);
        }
      } else {
        currentFilters[filterType] = currentFilters[filterType].filter(
          (val) => val !== filterValue
        );
      }

      currentPage = 1;
      loadDespachos();
      updateCurrentRefinements();
    });

    // Búsqueda en filtros optimizada
    $(".filter-search-input").on(
      "input",
      debounce(function () {
        const $input = $(this);
        const filterType = $input.data("filter");
        const searchTerm = $input.val() || "";

        // Mapear el tipo de filtro al ID correcto del contenedor
        let containerId;
        switch (filterType) {
          case "provincia":
            containerId = "provincias-filter";
            break;
          case "localidad":
            containerId = "localidades-filter";
            break;
          case "area":
            containerId = "areas-filter";
            break;
          default:
            return;
        }

        const container = $(`#${containerId}`);

        if (container.length === 0) {
          return;
        }

        container.find(".filter-item").each(function () {
          const text = $(this).find(".filter-text").text().toLowerCase();

          if (text.includes(searchTerm.toLowerCase())) {
            $(this).show();
          } else {
            $(this).hide();
          }
        });
      }, 200)
    );

    // Pestañas de filtros
    $(".filter-tab-btn").on("click", function () {
      const tab = $(this).data("tab");

      // Remover clase active de todos los botones y paneles
      $(".filter-tab-btn").removeClass("active");
      $(".filter-tab-pane").removeClass("active");

      // Agregar clase active al botón y panel seleccionado
      $(this).addClass("active");
      $(`#${tab}-list`).addClass("active");
    });

    // Letras del alfabeto
    $(".alphabet-letter").on("click", function () {
      if (isLoading) return; // Evitar clics durante carga

      $(".alphabet-letter").removeClass("active");
      $(this).addClass("active");

      const letter = $(this).data("letter");
      currentFilters.search = letter;
      $("#searchbox").val(letter);
      currentPage = 1;
      loadDespachos();
    });

    // Eliminar refinamientos
    $(document).on("click", ".refinement-remove", function () {
      if (isLoading) return; // Evitar clics durante carga

      const filterType = $(this).data("filter");
      const filterValue = $(this).data("value");

      currentFilters[filterType] = currentFilters[filterType].filter(
        (val) => val !== filterValue
      );

      // Desmarcar checkbox correspondiente
      $(
        `.filter-checkbox[data-filter="${filterType}"][value="${filterValue}"]`
      ).prop("checked", false);

      currentPage = 1;
      loadDespachos();
      updateCurrentRefinements();
    });

    // Event delegation para paginación
    $(document).on("click", ".pagination-link", function (e) {
      e.preventDefault();
      const newPage = parseInt($(this).data("page"));
      if (newPage !== currentPage) {
        currentPage = newPage;
        loadDespachos();
      }
    });

    // Event listeners para filtros
    $(document).on("change", ".filter-select", function () {
      currentPage = 1; // Reset a primera página
      updateFilters();
      loadDespachos();
    });

    // Event listener para búsqueda
    $(document).on("input", "#search-input", function () {
      clearTimeout(searchTimeout);
      currentPage = 1; // Reset a primera página
      currentFilters.search = $(this).val();
      searchTimeout = setTimeout(function () {
        loadDespachos();
      }, 500);
    });

    // Event listener para botón de búsqueda
    $(document).on("click", "#search-button", function (e) {
      e.preventDefault();
      currentPage = 1; // Reset a primera página
      currentFilters.search = $("#search-input").val();
      loadDespachos();
    });

    // Event listener para Enter en búsqueda
    $(document).on("keypress", "#search-input", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        currentPage = 1; // Reset a primera página
        currentFilters.search = $(this).val();
        loadDespachos();
      }
    });

    // Event listener para limpiar filtros
    $(document).on("click", "#clear-filters", function (e) {
      e.preventDefault();
      currentPage = 1; // Reset a primera página
      currentFilters = {
        search: "",
        provincia: [],
        localidad: [],
        area: [],
      };
      $("#search-input").val("");
      $(".filter-select").val("").trigger("change");
      loadDespachos();
    });
  }

  function loadDespachos() {
    if (isLoading) {
      return; // Evitar múltiples requests
    }

    isLoading = true;
    const data = {
      action: "lexhoy_despachos_search",
      nonce: lexhoyDespachosData.nonce,
      search: currentFilters.search,
      provincia: currentFilters.provincia.join(","),
      localidad: currentFilters.localidad.join(","),
      area: currentFilters.area.join(","),
      page: currentPage,
    };

    // Mostrar loading con spinner
    $("#hits").html(
      '<div class="loading-message"><i class="fas fa-spinner fa-spin"></i> Buscando despachos...</div>'
    );

    $.ajax({
      url: lexhoyDespachosData.ajaxUrl,
      type: "POST",
      data: data,
      timeout: 30000, // 30 segundos de timeout
      success: function (response) {
        if (response.success) {
          $("#hits").html(response.data.html);
          displayPagination(response.data.pagination);
          updateResultsCount(response.data.total);
        } else {
          $("#hits").html('<p class="error">Error al cargar los despachos</p>');
        }
        isLoading = false;
      },
      error: function (xhr, status, error) {
        isLoading = false;
        let errorMessage = "Error al conectar con el servidor";

        if (status === "timeout") {
          errorMessage = "La búsqueda tardó demasiado. Intenta de nuevo.";
        } else if (xhr.status === 500) {
          errorMessage =
            "Error interno del servidor. Contacta al administrador.";
        }

        $("#hits").html(
          '<div class="error-message">' + errorMessage + "</div>"
        );
      },
    });
  }

  function displayPagination(pagination) {
    const paginationContainer = $("#pagination");
    if (!pagination || pagination.total_pages <= 1) {
      paginationContainer.html("");
      return;
    }

    let paginationHTML = '<div class="pagination">';
    const totalPages = pagination.total_pages;
    const currentPageNum = pagination.current_page;

    // Botón anterior
    if (currentPageNum > 1) {
      paginationHTML += `<a href="#" class="pagination-link" data-page="${
        currentPageNum - 1
      }">← Anterior</a>`;
    }

    // Números de página - Mejorado para muchas páginas
    if (totalPages <= 10) {
      // Si hay 10 páginas o menos, mostrar todas
      for (let i = 1; i <= totalPages; i++) {
        if (i === currentPageNum) {
          paginationHTML += `<span class="pagination-current">${i}</span>`;
        } else {
          paginationHTML += `<a href="#" class="pagination-link" data-page="${i}">${i}</a>`;
        }
      }
    } else {
      // Si hay más de 10 páginas, mostrar estrategia inteligente

      // Siempre mostrar primera página
      if (currentPageNum === 1) {
        paginationHTML += `<span class="pagination-current">1</span>`;
      } else {
        paginationHTML += `<a href="#" class="pagination-link" data-page="1">1</a>`;
      }

      // Calcular rango de páginas a mostrar
      let startPage = Math.max(2, currentPageNum - 2);
      let endPage = Math.min(totalPages - 1, currentPageNum + 2);

      // Ajustar si estamos cerca del inicio
      if (currentPageNum <= 4) {
        endPage = Math.min(totalPages - 1, 6);
      }
      // Ajustar si estamos cerca del final
      if (currentPageNum >= totalPages - 3) {
        startPage = Math.max(2, totalPages - 5);
      }

      // Mostrar "..." si hay gap al inicio
      if (startPage > 2) {
        paginationHTML += `<span class="pagination-ellipsis">...</span>`;
      }

      // Mostrar páginas del rango
      for (let i = startPage; i <= endPage; i++) {
        if (i === currentPageNum) {
          paginationHTML += `<span class="pagination-current">${i}</span>`;
        } else {
          paginationHTML += `<a href="#" class="pagination-link" data-page="${i}">${i}</a>`;
        }
      }

      // Mostrar "..." si hay gap al final
      if (endPage < totalPages - 1) {
        paginationHTML += `<span class="pagination-ellipsis">...</span>`;
      }

      // Siempre mostrar última página
      if (currentPageNum === totalPages) {
        paginationHTML += `<span class="pagination-current">${totalPages}</span>`;
      } else {
        paginationHTML += `<a href="#" class="pagination-link" data-page="${totalPages}">${totalPages}</a>`;
      }
    }

    // Botón siguiente
    if (currentPageNum < totalPages) {
      paginationHTML += `<a href="#" class="pagination-link" data-page="${
        currentPageNum + 1
      }">Siguiente →</a>`;
    }

    paginationHTML += "</div>";

    paginationContainer.html(paginationHTML);
  }

  function updateCurrentRefinements() {
    let html = "";
    let hasRefinements = false;

    // Provincias
    if (currentFilters.provincia.length > 0) {
      hasRefinements = true;
      html += '<div class="refinement-group">';
      html += "<strong>Provincias:</strong>";
      currentFilters.provincia.forEach(function (provincia) {
        html += `<span class="refinement-item">
                    ${provincia}
                    <button class="refinement-remove" data-filter="provincia" data-value="${provincia}">×</button>
                </span>`;
      });
      html += "</div>";
    }

    // Localidades
    if (currentFilters.localidad.length > 0) {
      hasRefinements = true;
      html += '<div class="refinement-group">';
      html += "<strong>Localidades:</strong>";
      currentFilters.localidad.forEach(function (localidad) {
        html += `<span class="refinement-item">
                    ${localidad}
                    <button class="refinement-remove" data-filter="localidad" data-value="${localidad}">×</button>
                </span>`;
      });
      html += "</div>";
    }

    // Áreas
    if (currentFilters.area.length > 0) {
      hasRefinements = true;
      html += '<div class="refinement-group">';
      html += "<strong>Áreas:</strong>";
      currentFilters.area.forEach(function (area) {
        html += `<span class="refinement-item">
                    ${area}
                    <button class="refinement-remove" data-filter="area" data-value="${area}">×</button>
                </span>`;
      });
      html += "</div>";
    }

    if (hasRefinements) {
      $("#current-refinements").html(html);
    } else {
      $("#current-refinements").empty();
    }
  }

  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const context = this;
      const later = () => {
        clearTimeout(timeout);
        func.apply(context, args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  function updateFilters() {
    currentFilters.provincia = $("#provincia-filter").val() || [];
    currentFilters.localidad = $("#localidad-filter").val() || [];
    currentFilters.area = $("#area-filter").val() || [];
  }

  function updateResultsCount(total) {
    if (total === 0) {
      $("#results-count").text("No se encontraron despachos");
    } else if (total === 1) {
      $("#results-count").text("1 despacho encontrado");
    } else {
      $("#results-count").text(`${total} despachos encontrados`);
    }
  }
});
