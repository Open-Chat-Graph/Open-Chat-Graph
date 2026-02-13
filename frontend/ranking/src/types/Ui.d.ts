type TabPanelProps = React.PropsWithChildren<{
  index: number
  value: number
}>

type SortOptions = [[string, string], ListParams['order'], ListParams['sort']][]

type LinkEvent = React.MouseEvent<HTMLAnchorElement, MouseEvent>
type ClickEvent = React.MouseEvent<HTMLElement>

type SubCategoryChipsProps = {
  sub_category: ListParams['sub_category']
}

type KeywordChipProps = {
  keyword: ListParams['keyword']
}