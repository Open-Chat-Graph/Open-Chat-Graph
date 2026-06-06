import React from 'react'
import HighlightOffIcon from '@mui/icons-material/HighlightOff'
import { Box, IconButton, Input } from '@mui/material'
import useSiteHeaderSearch from '../hooks/useSiteHeaderSearch'
import { rankingArgDto } from '../config/config'
import { listParamsState } from '../store/atom'
import { useAtomValue } from 'jotai'
import { toggleButtons } from './ListToggleChips'
import { t } from '../config/translation'

export default function SiteHeaderSearch({
  children,
  headerInnerStyle,
  searchFormStyle,
  siperSlideTo,
}: {
  children?: React.ReactNode
  headerInnerStyle?: React.CSSProperties
  searchFormStyle?: React.CSSProperties
  siperSlideTo: (index: number) => void
}) {
  const {
    openSearch,
    closeSearch,
    onKeyDown,
    onChange,
    onSubmit,
    deleteInput,
    inputEmpty,
    inputRef,
    hiddenRef,
    buttonRef,
    open,
    handleCompositionStart,
    handleCompositionEnd,
  } = useSiteHeaderSearch(siperSlideTo)

  const params = useAtomValue(listParamsState)
  if (!toggleButtons.find((el) => el[0] === params.list))
    return (
      <header className="site_header_outer" id="site_header">
        <div
          className="site_header"
          style={{ ...headerInnerStyle, display: open ? 'none' : undefined }}
        >
          {children}
        </div>
      </header>
    )

  return (
    <header className="site_header_outer" id="site_header">
      <div
        className="site_header"
        style={{ ...headerInnerStyle, display: open ? 'none' : undefined }}
      >
        {children}
        <nav className="header-nav">
          {/* ダークモード切替（実装は public/js/theme.js のクリックデリゲーション） */}
          <button className="theme-toggle-btn" type="button" aria-label={t('ダークモード切替')}>
            <svg className="theme-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg className="theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          </button>
          <button
            className="header-button"
            id="search_button"
            aria-label={t('検索')}
            onClick={openSearch}
            ref={buttonRef}
          >
            <span className="search-button-icon"></span>
          </button>
        </nav>
      </div>
      <div hidden={!open}>
        <div
          className="backdrop"
          id="backdrop"
          role="button"
          aria-label={t('閉じる')}
          onClick={closeSearch}
        ></div>
        <form
          className="search-form site_header"
          style={searchFormStyle}
          method="GET"
          action={`${rankingArgDto.baseUrl}/search`}
          onSubmit={onSubmit}
        >
          <Box className="search-form-inner" sx={{ pt: '0px' }}>
            <label htmlFor="q" style={{ top: '10px' }}></label>
            <Input
              onKeyDown={onKeyDown}
              id="q"
              required
              autoComplete="off"
              placeholder={t('オープンチャットを検索')}
              inputRef={inputRef}
              slotProps={{
                input: {
                  'aria-label': 'weight',
                  sx: { pl: '2.1rem', pr: '3rem', m: '0.25rem 0' },
                  onChange: onChange,
                },
              }}
              sx={{ width: '100%' }}
              onCompositionStart={handleCompositionStart}
              onCompositionEnd={handleCompositionEnd}
              className="search-input"
            />
            <input type="hidden" name="q" ref={hiddenRef} />
            {!inputEmpty && (
              <IconButton
                sx={{ position: 'absolute', right: '5px', top: '7px', zIndex: 2004 }}
                onClick={deleteInput}
              >
                <HighlightOffIcon sx={{ fontSize: '22px', color: 'var(--c-text-3)' }} />
              </IconButton>
            )}
          </Box>
        </form>
      </div>
    </header>
  )
}
