import { DismissableLayerBranch } from '@radix-ui/react-dismissable-layer';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import InputError from '@/components/input-error';
import type { SearchableSelectOption } from '@/components/searchable-select';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    name: string;
    options: SearchableSelectOption[];
    defaultSelectedValues?: string[];
    placeholder?: string;
    searchPlaceholder?: string;
    searchable?: boolean;
    error?: string;
};

type Position = { top: number; left: number; width: number; maxHeight: number };

export default function SearchableMultiSelect({
    id,
    name,
    options,
    defaultSelectedValues = [],
    placeholder = 'Select options',
    searchPlaceholder = 'Search options...',
    searchable = options.length > 8,
    error,
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const defaultSelectionKey = defaultSelectedValues.join('|');
    const [selectionState, setSelectionState] = useState<{
        key: string;
        values: Set<string>;
    }>(() => ({
        key: defaultSelectionKey,
        values: new Set(defaultSelectedValues),
    }));
    const [position, setPosition] = useState<Position | null>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    const setMenuOpen = useCallback((nextOpen: boolean): void => {
        setOpen(nextOpen);
    }, []);
    const selectedValues =
        selectionState.key === defaultSelectionKey
            ? selectionState.values
            : new Set(defaultSelectedValues);

    const selectedOptions = options.filter((option) =>
        selectedValues.has(option.value),
    );
    const filteredOptions = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();

        if (!searchable || normalizedQuery === '') {
            return options;
        }

        return options.filter((option) =>
            `${option.label} ${option.searchText ?? ''}`
                .toLowerCase()
                .includes(normalizedQuery),
        );
    }, [options, query, searchable]);

    const updatePosition = (): void => {
        const trigger = triggerRef.current;

        if (trigger === null) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const padding = 16;
        const gap = 8;
        const width = Math.min(
            Math.max(rect.width, 280),
            window.innerWidth - padding * 2,
        );
        const below = window.innerHeight - rect.bottom - padding - gap;
        const above = rect.top - padding - gap;
        const opensAbove = below < 260 && above > below;
        const maxHeight = Math.min(
            360,
            Math.max(120, opensAbove ? above : below),
        );
        const top = opensAbove
            ? Math.max(padding, rect.top - maxHeight - gap)
            : rect.bottom + gap;
        const left = Math.min(
            Math.max(padding, rect.left),
            window.innerWidth - width - padding,
        );

        setPosition({ top, left, width, maxHeight });
    };

    const toggle = (): void => {
        if (open) {
            setMenuOpen(false);

            return;
        }

        updatePosition();
        setMenuOpen(true);
    };

    const toggleValue = (value: string): void => {
        setSelectionState(() => {
            const next = new Set(selectedValues);

            if (next.has(value)) {
                next.delete(value);
            } else {
                next.add(value);
            }

            return { key: defaultSelectionKey, values: next };
        });
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        const closeOnPointerDown = (event: PointerEvent): void => {
            const target = event.target as Node;

            if (
                !triggerRef.current?.contains(target) &&
                !menuRef.current?.contains(target)
            ) {
                setMenuOpen(false);
            }
        };
        const closeOnEscape = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        };

        document.addEventListener('pointerdown', closeOnPointerDown);
        document.addEventListener('keydown', closeOnEscape);
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);

        return () => {
            document.removeEventListener('pointerdown', closeOnPointerDown);
            document.removeEventListener('keydown', closeOnEscape);
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
        };
    }, [open, setMenuOpen]);

    const menu =
        open && position !== null && typeof document !== 'undefined'
            ? createPortal(
                  <DismissableLayerBranch asChild>
                      <div
                          ref={menuRef}
                          role="listbox"
                          aria-label={placeholder}
                          aria-multiselectable="true"
                          onPointerDown={(event) => {
                              event.stopPropagation();
                              event.nativeEvent.stopImmediatePropagation();
                          }}
                          className="pointer-events-auto fixed z-[100] overflow-hidden rounded-lg border bg-popover p-1.5 text-popover-foreground shadow-xl"
                          style={position}
                      >
                          {searchable && (
                              <div className="relative p-1">
                                  <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                  <input
                                      type="search"
                                      value={query}
                                      onChange={(event) =>
                                          setQuery(event.target.value)
                                      }
                                      placeholder={searchPlaceholder}
                                      autoFocus
                                      className="h-9 w-full rounded-md border bg-background pr-3 pl-9 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                  />
                              </div>
                          )}
                          <div
                              className="overflow-y-auto"
                              style={{
                                  maxHeight: Math.max(
                                      80,
                                      position.maxHeight -
                                          (searchable ? 56 : 8),
                                  ),
                              }}
                          >
                              {filteredOptions.length === 0 ? (
                                  <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                      No matching options.
                                  </p>
                              ) : (
                                  filteredOptions.map((option) => {
                                      const selected = selectedValues.has(
                                          option.value,
                                      );

                                      return (
                                          <button
                                              key={option.value}
                                              type="button"
                                              role="option"
                                              aria-selected={selected}
                                              onClick={() =>
                                                  toggleValue(option.value)
                                              }
                                              className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors outline-none hover:bg-accent focus-visible:bg-accent"
                                          >
                                              <span
                                                  className={cn(
                                                      'flex size-4 shrink-0 items-center justify-center rounded-[4px] border',
                                                      selected &&
                                                          'border-primary bg-primary text-primary-foreground',
                                                  )}
                                              >
                                                  {selected && (
                                                      <Check className="size-3" />
                                                  )}
                                              </span>
                                              <span className="min-w-0 truncate">
                                                  {option.label}
                                              </span>
                                          </button>
                                      );
                                  })
                              )}
                          </div>
                      </div>
                  </DismissableLayerBranch>,
                  document.body,
              )
            : null;

    return (
        <>
            <div className="relative">
                <button
                    ref={triggerRef}
                    id={id}
                    type="button"
                    aria-expanded={open}
                    aria-haspopup="listbox"
                    onClick={toggle}
                    className={cn(
                        'flex min-h-9 w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-1.5 text-left text-sm shadow-xs transition-colors outline-none',
                        'hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        error && 'border-destructive',
                    )}
                >
                    <span className="flex min-w-0 flex-1 flex-wrap gap-1.5">
                        {selectedOptions.length > 0 ? (
                            selectedOptions.map((option) => (
                                <span
                                    key={option.value}
                                    className="inline-flex max-w-full items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs font-medium"
                                >
                                    <span className="truncate">
                                        {option.label}
                                    </span>
                                    <X className="size-3 text-muted-foreground" />
                                </span>
                            ))
                        ) : (
                            <span className="text-muted-foreground">
                                {placeholder}
                            </span>
                        )}
                    </span>
                    <ChevronDown
                        className={cn(
                            'size-4 shrink-0 text-muted-foreground transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </button>
                {selectedOptions.map((option) => (
                    <input
                        key={option.value}
                        type="hidden"
                        name={name}
                        value={option.value}
                    />
                ))}
            </div>
            <InputError message={error} className="mt-1" />
            {menu}
        </>
    );
}
