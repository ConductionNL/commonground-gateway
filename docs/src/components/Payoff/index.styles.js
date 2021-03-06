import styled from 'styled-components/macro'
import { mediaQueries } from '@commonground/design-system'

export const StyledContainer = styled.div`
  display: flex;

  ${mediaQueries.mdUp`
    display: grid;
    grid-template-columns: 50% 50%;
    grid-template-columns: 100%;
  `}

  margin-top: 40px;
  margin-bottom: 40px;
`

export const StyledTextContainer = styled.div`
  display: flex;
  flex-direction: column;
  justify-content: center;
`

export const StyledIntro = styled.div`
`

export const StyledImage = styled.div`
  display: none;

  ${mediaQueries.mdUp`
    display: block;
  `}
`

export const StyledHeading = styled.h1`
  color: white;
  font-size: 34px;
  font-weight: ${(p) => p.theme.tokens.fontWeightBold};
`

export const StyledParagraph = styled.p`
  color: white;
  font-size: 24px;
  font-style: italic;
  line-height: 30px;
`

export const StyledSubParagraph = styled.p`
  color: white;
  font-size: 18px;
  line-height: 30px;


  a {
    color: #ffffff !important;
  }

`
