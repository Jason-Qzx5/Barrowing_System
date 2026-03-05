(function () {
    "use strict";

    const DEFAULT_PAGE_SIZE = 10;
    const instances = new Map();

    function getDataRows(table) {
        if (!table || !table.tBodies || table.tBodies.length === 0) {
            return [];
        }
        return Array.from(table.tBodies[0].rows);
    }

    function isFilterMatch(row) {
        return row.dataset.filterMatch !== "0";
    }

    function getPagerAnchor(table) {
        return table.closest(".table-shell")
            || table.closest(".equipment-table-wrap")
            || table;
    }

    function ensurePager(instance) {
        if (instance.pagerWrap) {
            return;
        }

        const pagerWrap = document.createElement("div");
        pagerWrap.className = "next-only-pagination";

        const nextBtn = document.createElement("button");
        nextBtn.type = "button";
        nextBtn.className = "next-page-btn";
        nextBtn.textContent = instance.nextLabel;

        nextBtn.addEventListener("click", function () {
            if (nextBtn.disabled) {
                return;
            }
            instance.currentPage += 1;
            render(instance);
        });

        pagerWrap.appendChild(nextBtn);

        const anchor = getPagerAnchor(instance.table);
        anchor.insertAdjacentElement("afterend", pagerWrap);

        instance.pagerWrap = pagerWrap;
        instance.nextBtn = nextBtn;
    }

    function render(instance) {
        const allRows = getDataRows(instance.table);
        const filteredRows = allRows.filter(instance.filterPredicate);
        const pageSize = instance.pageSize;
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));

        if (instance.currentPage >= pageCount) {
            instance.currentPage = pageCount - 1;
        }
        if (instance.currentPage < 0) {
            instance.currentPage = 0;
        }

        const startIndex = instance.currentPage * pageSize;
        const endIndex = startIndex + pageSize;
        const pageRows = new Set(filteredRows.slice(startIndex, endIndex));

        allRows.forEach(function (row) {
            const shouldShow = pageRows.has(row);
            row.style.display = shouldShow ? "" : "none";
        });

        if (instance.pagerWrap) {
            instance.pagerWrap.style.display = filteredRows.length > pageSize ? "flex" : "none";
        }
        if (instance.nextBtn) {
            instance.nextBtn.disabled = (instance.currentPage + 1) >= pageCount;
        }
    }

    function init(table, options) {
        if (!table) {
            return null;
        }

        const existing = instances.get(table);
        if (existing) {
            if (options && options.resetPage) {
                existing.currentPage = 0;
            }
            render(existing);
            return existing;
        }

        const normalizedOptions = options || {};
        const instance = {
            table: table,
            pageSize: Number(normalizedOptions.pageSize) || DEFAULT_PAGE_SIZE,
            currentPage: 0,
            nextLabel: normalizedOptions.nextLabel || "Next",
            filterPredicate: normalizedOptions.filterPredicate || isFilterMatch,
            pagerWrap: null,
            nextBtn: null
        };

        ensurePager(instance);
        instances.set(table, instance);
        render(instance);
        return instance;
    }

    function refresh(table, resetPage) {
        const instance = instances.get(table);
        if (!instance) {
            return init(table, { resetPage: !!resetPage });
        }

        if (resetPage) {
            instance.currentPage = 0;
        }
        render(instance);
        return instance;
    }

    window.setupNextOnlyTablePagination = init;
    window.refreshNextOnlyTablePagination = refresh;

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("table.js-next-pagination").forEach(function (table) {
            init(table, { pageSize: DEFAULT_PAGE_SIZE });
        });
    });
})();
