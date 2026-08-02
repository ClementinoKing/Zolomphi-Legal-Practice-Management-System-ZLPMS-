import { DismissableLayerBranch } from '@radix-ui/react-dismissable-layer';
import { Check, ChevronDown, Search } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

export type SearchableSelectOption = {
    value: string;
    label: string;
    searchText?: string;
};

type Props = {
    id?: string;
    name?: string;
    value?: string;
    defaultValue?: string;
    options: SearchableSelectOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyLabel?: string;
    searchable?: boolean;
    required?: boolean;
    dataStep?: number;
    disabled?: boolean;
    error?: string;
    className?: string;
    onChange?: (value: string) => void;
};

type Position = { top: number; left: number; width: number; maxHeight: number };

export default function SearchableSelect({
    id,
    name,
    value,
    defaultValue = '',
    options,
    placeholder = 'Select an option',
    searchPlaceholder = 'Search options...',
    emptyLabel = 'No options found.',
    searchable = options.length > 8,
    required = false,
    dataStep,
    disabled = false,
    error,
    className,
    onChange,
}: Props) {
    const isControlled = value !== undefined;
    const [uncontrolledState, setUncontrolledState] = useState({
        defaultValue,
        value: defaultValue,
    });
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [position, setPosition] = useState<Position | null>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    const setMenuOpen = useCallback((nextOpen: boolean): void => {
        setOpen(nextOpen);
    }, []);

    const selectedValue = isControlled
        ? value
        : uncontrolledState.defaultValue === defaultValue
          ? uncontrolledState.value
          : defaultValue;
    const selectedOption = options.find(
        (option) => option.value === selectedValue,
    );
    const filteredOptions = useMemo(() => {
        if (!searchable || query.trim() === '') {
            return options;
        }

        const normalizedQuery = query.trim().toLowerCase();

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
            Math.max(rect.width, 240),
            window.innerWidth - padding * 2,
        );
        const availableBelow = window.innerHeight - rect.bottom - padding - gap;
        const availableAbove = rect.top - padding - gap;
        const opensAbove =
            availableBelow < 260 && availableAbove > availableBelow;
        const maxHeight = Math.max(
            120,
            opensAbove ? availableAbove : availableBelow,
        );
        const top = opensAbove
            ? Math.max(padding, rect.top - Math.min(maxHeight, 360) - gap)
            : rect.bottom + gap;
        const left = Math.min(
            Math.max(padding, rect.left),
            window.innerWidth - width - padding,
        );

        setPosition({
            top,
            left,
            width,
            maxHeight: Math.min(maxHeight, 360),
        });
    };

    const toggle = (): void => {
        if (open) {
            setMenuOpen(false);

            return;
        }

        updatePosition();
        setMenuOpen(true);
    };

    const select = (nextValue: string): void => {
        if (!isControlled) {
            setUncontrolledState({ defaultValue, value: nextValue });
        }

        onChange?.(nextValue);
        setMenuOpen(false);
        setQuery('');
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
                          onPointerDown={(event) => {
                              event.stopPropagation();
                              event.nativeEvent.stopImmediatePropagation();
                          }}
                          className="pointer-events-auto fixed z-[100] overflow-hidden rounded-lg border bg-popover p-1.5 text-popover-foreground shadow-xl"
                          style={{
                              top: position.top,
                              left: position.left,
                              width: position.width,
                              maxHeight: position.maxHeight,
                          }}
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
                                      onKeyDown={(event) => {
                                          if (event.key === 'Enter') {
                                              event.preventDefault();
                                          }
                                      }}
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
                                      {emptyLabel}
                                  </p>
                              ) : (
                                  filteredOptions.map((option) => {
                                      const isSelected =
                                          option.value === selectedValue;

                                      return (
                                          <button
                                              key={option.value}
                                              type="button"
                                              role="option"
                                              aria-selected={isSelected}
                                              onClick={() =>
                                                  select(option.value)
                                              }
                                              className="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors outline-none hover:bg-accent focus-visible:bg-accent"
                                          >
                                              <span className="min-w-0 truncate">
                                                  {option.label}
                                              </span>
                                              {isSelected && (
                                                  <Check className="size-4 shrink-0 text-primary" />
                                              )}
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
                    aria-required={required}
                    disabled={disabled}
                    onClick={toggle}
                    className={cn(
                        'flex h-9 w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 text-left text-sm shadow-xs transition-colors outline-none',
                        'hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        'disabled:cursor-not-allowed disabled:opacity-60',
                        error && 'border-destructive',
                        className,
                    )}
                >
                    <span
                        className={cn(
                            'min-w-0 truncate',
                            selectedOption === undefined &&
                                'text-muted-foreground',
                        )}
                    >
                        {selectedOption?.label ?? placeholder}
                    </span>
                    <ChevronDown
                        className={cn(
                            'size-4 shrink-0 text-muted-foreground transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </button>
                {name && (
                    <input
                        type="hidden"
                        name={name}
                        value={selectedValue ?? ''}
                        data-client-step={dataStep}
                        readOnly
                    />
                )}
            </div>
            <InputError message={error} className="mt-1" />
            {menu}
        </>
    );
}
