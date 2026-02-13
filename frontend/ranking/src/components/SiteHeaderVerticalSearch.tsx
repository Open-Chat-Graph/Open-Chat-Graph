import React from 'react'
import HighlightOffIcon from '@mui/icons-material/HighlightOff'
import { Box, IconButton, Input } from '@mui/material'
import useSiteHeaderSearch from '../hooks/useSiteHeaderSearch'
import { rankingArgDto } from '../config/config'
import { toggleButtons } from './ListToggleChips'
import { listParamsState } from '../store/atom'
import { useRecoilValue } from 'recoil'
import { t } from '../config/translation'

export default function SiteHeaderVerticalSearch() {
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
  } = useSiteHeaderSearch()

  const params = useRecoilValue(listParamsState)
  if (!toggleButtons.find((el) => el[0] === params.list)) return null

  return (
    <div style={{ position: 'absolute', top: '6px', right: '1rem' }}>
      <header className='site_header_outer' id='site_header'>
        <div className='site_header'>
          <nav className='header-nav'>
            <button
              className='header-button'
              id='search_button'
              aria-label={t('検索')}
              onClick={openSearch}
              ref={buttonRef}
            >
              <span className='search-button-icon'></span>
            </button>
          </nav>
        </div>
        <div hidden={!open}>
          <div
            className='backdrop'
            id='backdrop'
            role='button'
            aria-label={t('閉じる')}
            onClick={closeSearch}
          ></div>
          <form
            className='search-form'
            method='GET'
            action={`${rankingArgDto.baseUrl}/search`}
            style={{
              background: '#fff',
              padding: '0.5rem',
              boxShadow: 'rgba(0, 0, 0, 0.3) 0px 3px 8px',
              borderRadius: '4px',
              position: 'absolute',
              width: '300px',
              left: '-296px',
              top: '13px',
            }}
            onSubmit={onSubmit}
          >
            <Box className='search-form-inner'>
              <label htmlFor='q' style={{ top: '9px' }}></label>
              <Input
                onKeyDown={onKeyDown}
                id='q'
                required
                autoComplete='off'
                placeholder={t('オープンチャットを検索')}
                inputProps={{
                  'aria-label': 'weight',
                  sx: { pl: '2.1rem', pr: '3rem', m: '0.25rem 0' },
                  ref: inputRef,
                  onChange: onChange,
                }}
                sx={{ width: '100%' }}
                onCompositionStart={handleCompositionStart}
                onCompositionEnd={handleCompositionEnd}
                className='search-input'
              />
              <input type='hidden' name='q' ref={hiddenRef} />
              {!inputEmpty && (
                <IconButton
                  sx={{ position: 'absolute', right: '5px', top: '0px', zIndex: 2004 }}
                  onClick={deleteInput}
                >
                  <HighlightOffIcon sx={{ fontSize: '22px', color: '#777' }} />
                </IconButton>
              )}
            </Box>
          </form>
        </div>
      </header>
    </div>
  )
}
