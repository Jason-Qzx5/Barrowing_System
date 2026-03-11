(function () {
    "use strict";

    const DEFAULT_PAGE_SIZE = 10;
    const DEFAULT_MAX_PAGE_BUTTONS = 7;
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

    function buildPageButton(instance, pageIndex) {
        const btn = document.createElement("button");
        const isActive = pageIndex === instance.currentPage;

        btn.type = "button";
        btn.className = "pagination-num-btn" + (isActive ? " is-active" : "");
        btn.textContent = String(pageIndex + 1);
        btn.disabled = isActive;
        if (isActive) {
            btn.setAttribute("aria-current", "page");
        }

        btn.addEventListener("click", function () {
            if (btn.disabled) {
                return;
            }
            instance.currentPage = pageIndex;
            render(instance);
        });

        return btn;
    }

    function buildEllipsis() {
        const gap = document.createElement("span");
        gap.className = "pagination-ellipsis";
        gap.textContent = "...";
        return gap;
    }

    function renderPageButtons(instance, pageCount) {
        if (!instance.pagesWrap) {
            return;
        }

        instance.pagesWrap.innerHTML = "";
        if (pageCount <= 1) {
            return;
        }

        const maxButtons = Math.max(3, instance.maxPageButtons);
        const fragment = document.createDocumentFragment();

        if (pageCount <= maxButtons) {
            for (let i = 0; i < pageCount; i += 1) {
                fragment.appendChild(buildPageButton(instance, i));
            }
            instance.pagesWrap.appendChild(fragment);
            return;
        }

        const edgeCount = 1;
        const middleCount = Math.max(1, maxButtons - (edgeCount * 2) - 2);
        let middleStart = instance.currentPage - Math.floor(middleCount / 2);
        let middleEnd = middleStart + middleCount - 1;

        if (middleStart < edgeCount) {
            middleStart = edgeCount;
            middleEnd = middleStart + middleCount - 1;
        }
        if (middleEnd > pageCount - edgeCount - 1) {
            middleEnd = pageCount - edgeCount - 1;
            middleStart = middleEnd - middleCount + 1;
        }

        for (let i = 0; i < edgeCount; i += 1) {
            fragment.appendChild(buildPageButton(instance, i));
        }

        if (middleStart > edgeCount) {
            fragment.appendChild(buildEllipsis());
        }

        for (let i = middleStart; i <= middleEnd; i += 1) {
            fragment.appendChild(buildPageButton(instance, i));
        }

        if (middleEnd < pageCount - edgeCount - 1) {
            fragment.appendChild(buildEllipsis());
        }

        for (let i = pageCount - edgeCount; i < pageCount; i += 1) {
            fragment.appendChild(buildPageButton(instance, i));
        }

        instance.pagesWrap.appendChild(fragment);
    }

    function ensurePager(instance) {
        if (instance.pagerWrap) {
            return;
        }

        const pagerWrap = document.createElement("div");
        pagerWrap.className = "next-only-pagination";

        const prevBtn = document.createElement("button");
        prevBtn.type = "button";
        prevBtn.className = "pagination-btn pagination-prev";
        prevBtn.textContent = instance.prevLabel;
        prevBtn.addEventListener("click", function () {
            if (prevBtn.disabled) {
                return;
            }
            instance.currentPage -= 1;
            render(instance);
        });

        const pagesWrap = document.createElement("div");
        pagesWrap.className = "pagination-pages";

        const nextBtn = document.createElement("button");
        nextBtn.type = "button";
        nextBtn.className = "pagination-btn pagination-next";
        nextBtn.textContent = instance.nextLabel;
        nextBtn.addEventListener("click", function () {
            if (nextBtn.disabled) {
                return;
            }
            instance.currentPage += 1;
            render(instance);
        });

        pagerWrap.appendChild(prevBtn);
        pagerWrap.appendChild(pagesWrap);
        pagerWrap.appendChild(nextBtn);

        const anchor = getPagerAnchor(instance.table);
        anchor.insertAdjacentElement("afterend", pagerWrap);

        instance.pagerWrap = pagerWrap;
        instance.prevBtn = prevBtn;
        instance.pagesWrap = pagesWrap;
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
        if (instance.prevBtn) {
            instance.prevBtn.disabled = instance.currentPage <= 0;
        }
        if (instance.nextBtn) {
            instance.nextBtn.disabled = (instance.currentPage + 1) >= pageCount;
        }

        renderPageButtons(instance, pageCount);
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
            prevLabel: normalizedOptions.prevLabel || "Previous",
            nextLabel: normalizedOptions.nextLabel || "Next",
            maxPageButtons: Number(normalizedOptions.maxPageButtons) || DEFAULT_MAX_PAGE_BUTTONS,
            filterPredicate: normalizedOptions.filterPredicate || isFilterMatch,
            pagerWrap: null,
            prevBtn: null,
            pagesWrap: null,
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

    function initTableIfEligible(table) {
        if (!table || table.dataset.noPagination === "1") {
            return;
        }
        init(table, { pageSize: DEFAULT_PAGE_SIZE });
    }

    function initAllTablesWithin(root) {
        if (!root || typeof root.querySelectorAll !== "function") {
            return;
        }
        root.querySelectorAll("table").forEach(initTableIfEligible);
    }

    window.setupNextOnlyTablePagination = init;
    window.refreshNextOnlyTablePagination = refresh;

    document.addEventListener("DOMContentLoaded", function () {
        initAllTablesWithin(document);

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }

                    if (node.tagName === "TABLE") {
                        initTableIfEligible(node);
                        return;
                    }

                    initAllTablesWithin(node);
                });
            });
        });

        if (document.body) {
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
})();
