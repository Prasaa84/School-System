import { ref, type Ref } from 'vue'

export type TableSortDirection = 'asc' | 'desc'

interface UseTableSortOptions<T, F extends string> {
  compare: (field: F, left: T, right: T) => number
  fallbackCompare?: (left: T, right: T) => number
  initialDirection?: TableSortDirection
}

interface UseTableSortResult<T, F extends string> {
  sortField: Ref<F | ''>
  sortDirection: Ref<TableSortDirection>
  toggleSort: (field: F) => void
  resetSort: () => void
  sortArrow: (field: F) => string
  sortArrowClass: (field: F) => string
  sortItems: (items: T[]) => T[]
}

export const useTableSort = <T, F extends string>(
  options: UseTableSortOptions<T, F>,
): UseTableSortResult<T, F> => {
  const sortField = ref('' as F | '') as Ref<F | ''>
  const sortDirection = ref<TableSortDirection>(options.initialDirection ?? 'asc')

  const toggleSort = (field: F): void => {
    if (sortField.value === field) {
      sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
      return
    }

    sortField.value = field
    sortDirection.value = options.initialDirection ?? 'asc'
  }

  const resetSort = (): void => {
    sortField.value = ''
    sortDirection.value = options.initialDirection ?? 'asc'
  }

  const sortArrow = (field: F): string => {
    if (sortField.value !== field) {
      return '↕'
    }

    return sortDirection.value === 'asc' ? '↑' : '↓'
  }

  const sortArrowClass = (field: F): string => (
    sortField.value === field ? 'text-slate-700' : 'text-slate-400'
  )

  const sortItems = (items: T[]): T[] => {
    if (sortField.value === '') {
      return items
    }

    return [...items].sort((left, right) => {
      let comparison = options.compare(sortField.value as F, left, right)

      if (comparison === 0 && options.fallbackCompare) {
        comparison = options.fallbackCompare(left, right)
      }

      return sortDirection.value === 'asc' ? comparison : comparison * -1
    })
  }

  return {
    sortField,
    sortDirection,
    toggleSort,
    resetSort,
    sortArrow,
    sortArrowClass,
    sortItems,
  }
}
